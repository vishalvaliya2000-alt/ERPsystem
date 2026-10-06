<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\ProductDocument;
use App\Models\ProductDocumentShare;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use ZipArchive;

class ProductDocumentController extends Controller
{
    private function getStorageDisk(): string
    {
        return config('filesystems.default') === 's3' ? 's3' : 'public';
    }

    /**
     * Get JSON list of all documents and photos for a product
     */
    public function getDocuments($productId)
    {
        $product = Product::with(['documents.createdByUser', 'shares'])->findOrFail($productId);

        $photos = $product->documents
            ->where('document_type', ProductDocument::TYPE_PHOTO)
            ->sortBy('sort_order')
            ->values()
            ->map(function ($p) {
                return array_merge($p->toArray(), [
                    'preview_url' => route('products.documents.preview', ['productId' => $p->product_id, 'docId' => $p->id]),
                    'download_url' => route('products.documents.download', ['productId' => $p->product_id, 'docId' => $p->id]),
                    'file_url' => Storage::disk($this->getStorageDisk())->url($p->file_path),
                ]);
            });

        $documents = $product->documents
            ->where('document_type', '!=', ProductDocument::TYPE_PHOTO)
            ->sortBy(fn ($d) => [$d->document_type, ! $d->is_latest, -$d->id])
            ->values()
            ->map(function ($d) {
                return array_merge($d->toArray(), [
                    'preview_url' => route('products.documents.preview', ['productId' => $d->product_id, 'docId' => $d->id]),
                    'download_url' => route('products.documents.download', ['productId' => $d->product_id, 'docId' => $d->id]),
                    'file_url' => Storage::disk($this->getStorageDisk())->url($d->file_path),
                    'is_expired' => $d->is_expired,
                    'days_until_expiry' => $d->days_until_expiry,
                    'type_label' => $d->type_label,
                    'type_icon' => $d->type_icon,
                    'type_badge_class' => $d->type_badge_class,
                    'formatted_file_size' => $d->formatted_file_size,
                    'created_at_human' => $d->created_at ? $d->created_at->format('d M Y') : '',
                ]);
            });

        return response()->json([
            'success' => true,
            'product' => [
                'id' => $product->id,
                'product_code' => $product->product_code,
                'product_name' => $product->product_name,
                'hsn_code' => $product->hsn_code,
                'category_name' => $product->category?->name ?? 'Product',
                'summary' => $product->document_badge_summary,
            ],
            'photos' => $photos,
            'documents' => $documents,
            'shares' => $product->shares->map(fn ($s) => [
                'id' => $s->id,
                'title' => $s->title,
                'share_url' => route('products.documents.shared', $s->share_token),
                'share_token' => $s->share_token,
                'has_password' => $s->has_password,
                'is_expired' => $s->is_expired,
                'expires_at_formatted' => $s->expires_at ? $s->expires_at->format('d M Y H:i') : 'Never',
                'views_count' => $s->views_count,
                'selected_document_ids' => $s->selected_document_ids,
                'created_at_formatted' => $s->created_at->format('d M Y'),
            ]),
        ]);
    }

    /**
     * Upload one or multiple product photos
     */
    public function uploadPhotos(Request $request, $productId)
    {
        $product = Product::findOrFail($productId);

        $request->validate([
            'photos' => 'required',
            'photos.*' => 'file|mimes:jpeg,jpg,png,webp,gif|max:20480', // max 20MB per photo
        ]);

        $files = $request->file('photos');
        if (! is_array($files)) {
            $files = [$files];
        }

        $existingPhotosCount = ProductDocument::where('product_id', $product->id)
            ->where('document_type', ProductDocument::TYPE_PHOTO)
            ->count();

        $disk = $this->getStorageDisk();
        $uploaded = [];

        DB::beginTransaction();
        try {
            foreach ($files as $index => $file) {
                if (! $file || ! $file->isValid()) {
                    continue;
                }

                $originalName = $file->getClientOriginalName();
                $extension = $file->getClientOriginalExtension();
                $mimeType = $file->getMimeType();
                $fileSize = $file->getSize();

                $storedName = Str::random(32).'.'.$extension;
                $folder = "product-media/{$product->tenant_id}/{$product->id}/photos";
                $filePath = $file->storeAs($folder, $storedName, $disk);

                $isPrimary = ($existingPhotosCount === 0 && $index === 0);
                $sortOrder = $existingPhotosCount + $index;

                $doc = ProductDocument::create([
                    'tenant_id' => $product->tenant_id,
                    'product_id' => $product->id,
                    'document_type' => ProductDocument::TYPE_PHOTO,
                    'title' => pathinfo($originalName, PATHINFO_FILENAME),
                    'file_path' => $filePath,
                    'file_name' => $originalName,
                    'file_size' => $fileSize,
                    'mime_type' => $mimeType,
                    'is_latest' => true,
                    'is_primary' => $isPrimary,
                    'sort_order' => $sortOrder,
                    'created_by_user_id' => auth()->id(),
                ]);

                $uploaded[] = $doc;
            }

            DB::commit();

            if ($request->wantsJson() || $request->ajax()) {
                return response()->json([
                    'success' => true,
                    'message' => count($uploaded).' photo(s) uploaded successfully.',
                    'photos' => $uploaded,
                ]);
            }

            return back()->with('success', count($uploaded).' photo(s) uploaded successfully.');
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('Failed to upload product photos: '.$e->getMessage(), [
                'product_id' => $productId,
                'exception' => $e,
            ]);

            if ($request->wantsJson() || $request->ajax()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to upload photos: '.$e->getMessage(),
                ], 422);
            }

            return back()->with('error', 'Failed to upload photos: '.$e->getMessage());
        }
    }

    /**
     * Upload a Product Document (COA, Spec Sheet, MSDS, Other)
     */
    public function uploadDocument(Request $request, $productId)
    {
        $product = Product::findOrFail($productId);

        // Normalize aliases before validation
        if ($request->has('document_type')) {
            $type = strtoupper(trim($request->input('document_type')));
            if ($type === 'SPECIFICATION_SHEET') {
                $type = ProductDocument::TYPE_SPECIFICATION;
            }
            $request->merge(['document_type' => $type]);
        }
        $request->validate([
            'document_type' => 'required|string|in:PHOTO,COA,SPECIFICATION,MSDS,OTHER',
            'file' => 'required_without:document|nullable|file|mimes:pdf,doc,docx,xls,xlsx,jpg,jpeg,png,webp,txt|max:51200', // max 50MB
            'document' => 'required_without:file|nullable|file|mimes:pdf,doc,docx,xls,xlsx,jpg,jpeg,png,webp,txt|max:51200', // max 50MB
            'title' => 'nullable|string|max:255',
            'file_name' => 'nullable|string|max:255',
            'version' => 'nullable|string|max:50',
            'valid_until' => 'nullable|date',
            'is_latest' => 'nullable|boolean',
            'notes' => 'nullable|string',
        ]);

        $file = $request->file('file') ?: $request->file('document');
        $originalName = $file->getClientOriginalName();
        $extension = $file->getClientOriginalExtension();
        $mimeType = $file->getMimeType();
        $fileSize = $file->getSize();

        $disk = $this->getStorageDisk();
        $type = strtoupper($request->input('document_type'));
        $title = $request->input('title') ?: ($request->input('file_name') ?: pathinfo($originalName, PATHINFO_FILENAME));
        $fileName = $request->input('file_name') ?: $originalName;
        if (! str_contains($fileName, '.') && ! empty($extension)) {
            $fileName .= '.'.$extension;
        }

        $version = $request->input('version') ?: null;
        $validUntil = $request->input('valid_until') ?: null;
        $isLatest = $request->boolean('is_latest', true);
        $notes = $request->input('notes') ?: null;

        $storedName = Str::random(32).'.'.$extension;
        $folder = "product-media/{$product->tenant_id}/{$product->id}/docs";
        $filePath = $file->storeAs($folder, $storedName, $disk);

        DB::beginTransaction();
        try {
            // If marked as latest, unmark previous latest of same type
            if ($isLatest) {
                ProductDocument::where('product_id', $product->id)
                    ->where('document_type', $type)
                    ->update(['is_latest' => false]);
            }

            $doc = ProductDocument::create([
                'tenant_id' => $product->tenant_id,
                'product_id' => $product->id,
                'document_type' => $type,
                'title' => $title,
                'file_path' => $filePath,
                'file_name' => $fileName,
                'file_size' => $fileSize,
                'mime_type' => $mimeType,
                'version' => $version,
                'valid_until' => $validUntil,
                'is_latest' => $isLatest,
                'is_primary' => false,
                'sort_order' => 0,
                'notes' => $notes,
                'created_by_user_id' => auth()->id(),
            ]);

            DB::commit();

            if ($request->wantsJson() || $request->ajax()) {
                return response()->json([
                    'success' => true,
                    'message' => "Document '{$originalName}' uploaded successfully.",
                    'document' => $doc,
                ]);
            }

            return back()->with('success', "✓ Document '{$originalName}' uploaded successfully.");
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('Failed to upload document: '.$e->getMessage(), [
                'product_id' => $productId,
                'exception' => $e,
            ]);

            if ($request->wantsJson() || $request->ajax()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to upload document: '.$e->getMessage(),
                ], 422);
            }

            return back()->with('error', 'Failed to upload document: '.$e->getMessage());
        }
    }

    /**
     * Set a photo as the primary product image
     */
    public function setPrimaryPhoto(Request $request, $productId, $docId)
    {
        $product = Product::findOrFail($productId);
        $doc = ProductDocument::where('product_id', $product->id)->findOrFail($docId);

        if ($doc->document_type !== ProductDocument::TYPE_PHOTO) {
            return response()->json(['success' => false, 'message' => 'Document is not a photo.'], 422);
        }

        DB::beginTransaction();
        try {
            ProductDocument::where('product_id', $product->id)
                ->where('document_type', ProductDocument::TYPE_PHOTO)
                ->update(['is_primary' => false]);

            $doc->update(['is_primary' => true]);
            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Primary photo updated.',
            ]);
        } catch (\Throwable $e) {
            DB::rollBack();

            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Reorder photos
     */
    public function reorderPhotos(Request $request, $productId)
    {
        $product = Product::findOrFail($productId);
        $photoIds = $request->input('photo_ids', []);

        if (! is_array($photoIds) || empty($photoIds)) {
            return response()->json(['success' => false, 'message' => 'Invalid photo IDs.'], 422);
        }

        DB::beginTransaction();
        try {
            foreach ($photoIds as $order => $id) {
                ProductDocument::where('product_id', $product->id)
                    ->where('document_type', ProductDocument::TYPE_PHOTO)
                    ->where('id', $id)
                    ->update(['sort_order' => (int) $order]);
            }
            DB::commit();

            return response()->json(['success' => true, 'message' => 'Photos reordered successfully.']);
        } catch (\Throwable $e) {
            DB::rollBack();

            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Toggle document latest version flag
     */
    public function toggleLatest(Request $request, $productId, $docId)
    {
        $product = Product::findOrFail($productId);
        $doc = ProductDocument::where('product_id', $product->id)->findOrFail($docId);

        $newVal = ! $doc->is_latest;

        DB::beginTransaction();
        try {
            if ($newVal) {
                ProductDocument::where('product_id', $product->id)
                    ->where('document_type', $doc->document_type)
                    ->where('id', '!=', $doc->id)
                    ->update(['is_latest' => false]);
            }

            $doc->update(['is_latest' => $newVal]);
            DB::commit();

            return response()->json([
                'success' => true,
                'is_latest' => $doc->is_latest,
                'message' => $doc->is_latest ? 'Marked as latest version.' : 'Unmarked from latest.',
            ]);
        } catch (\Throwable $e) {
            DB::rollBack();

            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Preview document inline in browser
     */
    public function preview($productId, $docId)
    {
        $product = Product::findOrFail($productId);
        $doc = ProductDocument::where('product_id', $product->id)->findOrFail($docId);

        $disk = $this->getStorageDisk();
        if (! Storage::disk($disk)->exists($doc->file_path)) {
            abort(404, 'File not found on storage.');
        }

        $mimeType = $doc->mime_type ?: Storage::disk($disk)->mimeType($doc->file_path) ?: 'application/octet-stream';
        $fileContent = Storage::disk($disk)->get($doc->file_path);

        return Response::make($fileContent, 200, [
            'Content-Type' => $mimeType,
            'Content-Disposition' => 'inline; filename="'.addslashes($doc->file_name).'"',
            'Cache-Control' => 'private, max-age=86400',
        ]);
    }

    /**
     * Download document as attachment
     */
    public function download($productId, $docId)
    {
        $product = Product::findOrFail($productId);
        $doc = ProductDocument::where('product_id', $product->id)->findOrFail($docId);

        $disk = $this->getStorageDisk();
        if (! Storage::disk($disk)->exists($doc->file_path)) {
            abort(404, 'File not found on storage.');
        }

        return Storage::disk($disk)->download($doc->file_path, $doc->file_name);
    }

    /**
     * Download all documents and photos of a product as a ZIP
     */
    public function downloadAllZip($productId)
    {
        $product = Product::with('documents')->findOrFail($productId);
        $docs = $product->documents;

        if ($docs->isEmpty()) {
            return back()->with('error', 'No documents available to download.');
        }

        $disk = $this->getStorageDisk();
        $zipFileName = Str::slug($product->product_code ?: $product->product_name).'_Documents_'.date('Ymd').'.zip';
        $tempZipPath = tempnam(sys_get_temp_dir(), 'zip_');

        $zip = new ZipArchive;
        if ($zip->open($tempZipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            abort(500, 'Could not create ZIP file.');
        }

        $addedNames = [];
        foreach ($docs as $doc) {
            if (Storage::disk($disk)->exists($doc->file_path)) {
                $content = Storage::disk($disk)->get($doc->file_path);

                // Group inside folders in the ZIP: Photos, COA, Specifications, MSDS, Other
                $folder = match ($doc->document_type) {
                    ProductDocument::TYPE_PHOTO => 'Photos',
                    ProductDocument::TYPE_COA => 'COA',
                    ProductDocument::TYPE_SPECIFICATION => 'Specifications',
                    ProductDocument::TYPE_MSDS => 'MSDS',
                    default => 'Other_Documents',
                };

                $filename = $doc->file_name;
                $zipEntryName = "{$folder}/{$filename}";

                // Prevent duplicate filenames in same zip folder
                $counter = 1;
                while (in_array($zipEntryName, $addedNames)) {
                    $pi = pathinfo($filename);
                    $nameOnly = $pi['filename'];
                    $ext = ! empty($pi['extension']) ? '.'.$pi['extension'] : '';
                    $zipEntryName = "{$folder}/{$nameOnly}_{$counter}{$ext}";
                    $counter++;
                }

                $addedNames[] = $zipEntryName;
                $zip->addFromString($zipEntryName, $content);
            }
        }

        $zip->close();

        return response()->download($tempZipPath, $zipFileName)->deleteFileAfterSend(true);
    }

    /**
     * Delete document or photo
     */
    public function destroy($productId, $docId)
    {
        $product = Product::findOrFail($productId);
        $doc = ProductDocument::where('product_id', $product->id)->findOrFail($docId);

        $disk = $this->getStorageDisk();
        $wasPrimary = $doc->is_primary;
        $isPhoto = $doc->document_type === ProductDocument::TYPE_PHOTO;

        DB::beginTransaction();
        try {
            if (Storage::disk($disk)->exists($doc->file_path)) {
                Storage::disk($disk)->delete($doc->file_path);
            }

            $doc->delete();

            // If deleted photo was primary, elect another photo
            if ($wasPrimary && $isPhoto) {
                $nextPhoto = ProductDocument::where('product_id', $product->id)
                    ->where('document_type', ProductDocument::TYPE_PHOTO)
                    ->orderBy('sort_order')
                    ->first();
                if ($nextPhoto) {
                    $nextPhoto->update(['is_primary' => true]);
                }
            }

            DB::commit();

            if (request()->wantsJson() || request()->ajax()) {
                return response()->json([
                    'success' => true,
                    'message' => 'Document deleted successfully.',
                ]);
            }

            return back()->with('success', 'Document deleted successfully.');
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('Failed to delete product document: '.$e->getMessage());

            if (request()->wantsJson() || request()->ajax()) {
                return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
            }

            return back()->with('error', 'Failed to delete document: '.$e->getMessage());
        }
    }

    /**
     * Create a public, time-limited share link for selected documents/photos
     */
    public function createShare(Request $request, $productId)
    {
        $product = Product::findOrFail($productId);

        $request->validate([
            'title' => 'nullable|string|max:255',
            'recipient_email' => 'nullable|email|max:255',
            'selected_document_ids' => 'nullable|array',
            'selected_document_ids.*' => 'integer|exists:product_documents,id',
            'expires_in' => 'required|string|in:24h,7d,30d,90d,never',
            'password' => 'nullable|string|min:4|max:50',
        ]);

        $expiresAt = match ($request->input('expires_in')) {
            '24h' => Carbon::now()->addDay(),
            '7d' => Carbon::now()->addDays(7),
            '30d' => Carbon::now()->addDays(30),
            '90d' => Carbon::now()->addDays(90),
            default => null,
        };

        $passwordHash = null;
        if (! empty($request->input('password'))) {
            $passwordHash = Hash::make($request->input('password'));
        }

        $selectedIds = $request->input('selected_document_ids');
        if (empty($selectedIds)) {
            // Default to all documents and photos
            $selectedIds = ProductDocument::where('product_id', $product->id)->pluck('id')->toArray();
        }

        $token = Str::random(40);

        $share = ProductDocumentShare::create([
            'tenant_id' => $product->tenant_id,
            'product_id' => $product->id,
            'share_token' => $token,
            'title' => $request->input('title') ?: "Product Documentation - {$product->product_code}",
            'recipient_email' => $request->input('recipient_email'),
            'selected_document_ids' => $selectedIds,
            'password_hash' => $passwordHash,
            'expires_at' => $expiresAt,
            'created_by_user_id' => auth()->id(),
        ]);

        $shareUrl = route('products.documents.shared', ['shareToken' => $token]);

        return response()->json([
            'success' => true,
            'message' => 'Secure share link generated successfully.',
            'share_url' => $shareUrl,
            'share_token' => $token,
            'expires_at' => $expiresAt ? $expiresAt->format('d M Y, h:i A') : 'Never',
            'has_password' => ! empty($passwordHash),
            'share' => $share,
        ]);
    }

    /**
     * Revoke / delete a share link
     */
    public function deleteShare(Request $request, $productId, $shareId)
    {
        $product = Product::findOrFail($productId);
        $share = ProductDocumentShare::where('product_id', $product->id)->findOrFail($shareId);
        $share->delete();

        return response()->json([
            'success' => true,
            'message' => 'Share link revoked successfully.',
        ]);
    }

    // =========================================================================
    // PUBLIC CUSTOMER SHARE PORTAL (No login required)
    // =========================================================================

    /**
     * View shared product documentation portal
     */
    public function viewShared(Request $request, $shareToken)
    {
        $share = ProductDocumentShare::with(['product.category', 'product.documents'])
            ->where('share_token', $shareToken)
            ->firstOrFail();

        // 1. Check expiration
        if ($share->is_expired) {
            return view('products.share_expired', compact('share'));
        }

        // 2. Check password protection
        if ($share->has_password) {
            $sessionKey = "doc_share_verified_{$share->id}";
            if (! $request->session()->get($sessionKey)) {
                return view('products.share_password', compact('share'));
            }
        }

        // 3. Track view count
        $share->increment('views_count');
        $share->update(['last_accessed_at' => Carbon::now()]);

        // 4. Retrieve shared documents
        $allDocs = $share->getDocuments();
        $photos = $allDocs->where('document_type', ProductDocument::TYPE_PHOTO)->sortBy('sort_order')->values();
        $documents = $allDocs->where('document_type', '!=', ProductDocument::TYPE_PHOTO)
            ->sortBy(fn ($d) => [$d->document_type, ! $d->is_latest, -$d->id])
            ->values();

        $product = $share->product;

        return view('products.share', compact('share', 'product', 'photos', 'documents'));
    }

    /**
     * Verify password for protected share
     */
    public function verifySharePassword(Request $request, $shareToken)
    {
        $share = ProductDocumentShare::where('share_token', $shareToken)->firstOrFail();

        $request->validate([
            'password' => 'required|string',
        ]);

        if ($share->verifyPassword($request->input('password'))) {
            $sessionKey = "doc_share_verified_{$share->id}";
            $request->session()->put($sessionKey, true);

            return redirect()->route('products.documents.shared', $shareToken);
        }

        return back()->with('error', 'Incorrect password / access PIN. Please try again.');
    }

    /**
     * Public download of single document from share
     */
    public function downloadSharedDocument($shareToken, $docId)
    {
        $share = ProductDocumentShare::where('share_token', $shareToken)->firstOrFail();
        if ($share->is_expired) {
            abort(410, 'This share link has expired.');
        }

        if ($share->has_password && ! request()->session()->get("doc_share_verified_{$share->id}")) {
            abort(403, 'Password required.');
        }

        if (! empty($share->selected_document_ids) && ! in_array((int) $docId, $share->selected_document_ids)) {
            abort(403, 'Document not included in this share.');
        }

        $doc = ProductDocument::where('product_id', $share->product_id)->findOrFail($docId);
        $disk = $this->getStorageDisk();

        if (! Storage::disk($disk)->exists($doc->file_path)) {
            abort(404, 'File not found on storage.');
        }

        return Storage::disk($disk)->download($doc->file_path, $doc->file_name);
    }

    /**
     * Public preview of single document from share
     */
    public function previewSharedDocument($shareToken, $docId)
    {
        $share = ProductDocumentShare::where('share_token', $shareToken)->firstOrFail();
        if ($share->is_expired) {
            abort(410, 'This share link has expired.');
        }

        if ($share->has_password && ! request()->session()->get("doc_share_verified_{$share->id}")) {
            abort(403, 'Password required.');
        }

        if (! empty($share->selected_document_ids) && ! in_array((int) $docId, $share->selected_document_ids)) {
            abort(403, 'Document not included in this share.');
        }

        $doc = ProductDocument::where('product_id', $share->product_id)->findOrFail($docId);
        $disk = $this->getStorageDisk();

        if (! Storage::disk($disk)->exists($doc->file_path)) {
            abort(404, 'File not found on storage.');
        }

        $mimeType = $doc->mime_type ?: Storage::disk($disk)->mimeType($doc->file_path) ?: 'application/octet-stream';
        $fileContent = Storage::disk($disk)->get($doc->file_path);

        return Response::make($fileContent, 200, [
            'Content-Type' => $mimeType,
            'Content-Disposition' => 'inline; filename="'.addslashes($doc->file_name).'"',
            'Cache-Control' => 'private, max-age=86400',
        ]);
    }

    /**
     * Public ZIP download of all shared documents
     */
    public function downloadSharedZip($shareToken)
    {
        $share = ProductDocumentShare::with('product')->where('share_token', $shareToken)->firstOrFail();
        if ($share->is_expired) {
            abort(410, 'This share link has expired.');
        }

        if ($share->has_password && ! request()->session()->get("doc_share_verified_{$share->id}")) {
            abort(403, 'Password required.');
        }

        $product = $share->product;
        $docs = $share->getDocuments();

        if ($docs->isEmpty()) {
            abort(404, 'No documents available to download.');
        }

        $disk = $this->getStorageDisk();
        $zipFileName = Str::slug($product->product_code ?: $product->product_name).'_Documentation.zip';
        $tempZipPath = tempnam(sys_get_temp_dir(), 'shared_zip_');

        $zip = new ZipArchive;
        if ($zip->open($tempZipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            abort(500, 'Could not create ZIP file.');
        }

        $addedNames = [];
        foreach ($docs as $doc) {
            if (Storage::disk($disk)->exists($doc->file_path)) {
                $content = Storage::disk($disk)->get($doc->file_path);

                $folder = match ($doc->document_type) {
                    ProductDocument::TYPE_PHOTO => 'Photos',
                    ProductDocument::TYPE_COA => 'COA',
                    ProductDocument::TYPE_SPECIFICATION => 'Specifications',
                    ProductDocument::TYPE_MSDS => 'MSDS',
                    default => 'Other_Documents',
                };

                $filename = $doc->file_name;
                $zipEntryName = "{$folder}/{$filename}";

                $counter = 1;
                while (in_array($zipEntryName, $addedNames)) {
                    $pi = pathinfo($filename);
                    $nameOnly = $pi['filename'];
                    $ext = ! empty($pi['extension']) ? '.'.$pi['extension'] : '';
                    $zipEntryName = "{$folder}/{$nameOnly}_{$counter}{$ext}";
                    $counter++;
                }

                $addedNames[] = $zipEntryName;
                $zip->addFromString($zipEntryName, $content);
            }
        }

        $zip->close();

        return response()->download($tempZipPath, $zipFileName)->deleteFileAfterSend(true);
    }
}
