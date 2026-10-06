<?php

namespace App\Http\Controllers;

use App\Models\BusinessDocument;
use App\Models\CommercialShipment;
use App\Models\DocumentVersion;
use App\Models\Invoice;
use App\Models\SalesOrder;
use App\Services\DocumentParserService;
use App\Services\TenantManager;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class DocumentController extends Controller
{
    protected DocumentParserService $parser;

    public function __construct(DocumentParserService $parser)
    {
        $this->parser = $parser;
    }

    /**
     * Document Centre page is removed. Redirect to Dashboard.
     */
    public function index(Request $request)
    {
        return redirect()->route('dashboard');
    }

    /**
     * AJAX Parse Endpoint: Extracts data from dropped file for user review screen.
     */
    public function parse(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:pdf,jpg,jpeg,png,webp,xlsx,xls,csv|max:20480',
            'document_type' => 'nullable|string|in:PO,INVOICE,LR',
        ]);

        $tenantId = TenantManager::getTenantId();
        $parsed = $this->parser->parse(
            $request->file('file'),
            $request->input('document_type'),
            $tenantId
        );

        return response()->json([
            'success' => true,
            'data' => $parsed,
        ]);
    }

    /**
     * Store finalized document after user verification on the Extracted Details screen.
     */
    public function store(Request $request)
    {
        $tenantId = TenantManager::getTenantId();

        $validated = $request->validate([
            'document_type' => 'required|string|in:PO,INVOICE,LR,OTHER',
            'document_number' => 'required|string|max:100',
            'customer_id' => 'required|exists:customers,id',
            'document_date' => 'required|date',
            'due_date' => 'nullable|date',
            'total_amount' => 'required|numeric|min:0',
            'taxable_amount' => 'nullable|numeric|min:0',
            'tax_amount' => 'nullable|numeric|min:0',
            'payment_terms' => 'nullable|string|max:150',
            'delivery_location' => 'nullable|string|max:255',
            'notes' => 'nullable|string',
            'status' => 'nullable|string|max:40',
            'sales_order_id' => 'nullable|exists:sales_orders,id',
            'commercial_shipment_id' => 'nullable|exists:commercial_shipments,id',
            'invoice_id' => 'nullable|exists:invoices,id',
            'file' => 'required|file|mimes:pdf,jpg,jpeg,png,webp,xlsx,xls,csv|max:25600',
            'items' => 'nullable|array',
            'items.*.product_id' => 'nullable|exists:products,id',
            'items.*.quantity' => 'nullable|numeric|min:0.01',
            'items.*.rate' => 'nullable|numeric|min:0.01',
        ]);

        DB::beginTransaction();
        try {
            $file = $request->file('file');
            $originalName = $file->getClientOriginalName();
            $ext = $file->getClientOriginalExtension();
            $safeDocNum = Str::slug($validated['document_number'], '_');
            $storageFilename = "doc_{$tenantId}_{$validated['document_type']}_{$safeDocNum}_".time().'.'.$ext;

            // Store file securely
            $storedPath = $file->storeAs("documents/{$tenantId}", $storageFilename, 'public');

            // 1. Create BusinessDocument Master Record
            $document = BusinessDocument::create([
                'tenant_id' => $tenantId,
                'document_type' => $validated['document_type'],
                'document_number' => strtoupper(trim($validated['document_number'])),
                'document_date' => Carbon::parse($validated['document_date']),
                'due_date' => ! empty($validated['due_date']) ? Carbon::parse($validated['due_date']) : null,
                'customer_id' => $validated['customer_id'],
                'sales_order_id' => $validated['sales_order_id'] ?? null,
                'commercial_shipment_id' => $validated['commercial_shipment_id'] ?? null,
                'invoice_id' => $validated['invoice_id'] ?? null,
                'total_amount' => (float) $validated['total_amount'],
                'taxable_amount' => (float) ($validated['taxable_amount'] ?? $validated['total_amount']),
                'tax_amount' => (float) ($validated['tax_amount'] ?? 0),
                'currency' => 'INR',
                'payment_terms' => $validated['payment_terms'] ?? '30 Days Credit',
                'delivery_location' => $validated['delivery_location'] ?? null,
                'status' => $validated['status'] ?? 'VERIFIED',
                'notes' => $validated['notes'] ?? null,
                'extracted_metadata' => [
                    'verified_items' => $request->input('items', []),
                    'uploader_ip' => $request->ip(),
                ],
                'current_version' => 1,
                'created_by_user_id' => auth()->id(),
            ]);

            // 2. Create Initial Document Version (v1)
            DocumentVersion::create([
                'document_id' => $document->id,
                'version_number' => 1,
                'file_path' => $storedPath,
                'file_name' => $originalName,
                'file_size' => $file->getSize(),
                'mime_type' => $file->getMimeType(),
                'uploaded_by_user_id' => auth()->id(),
                'change_note' => 'Initial upload and verification',
                'is_active' => true,
            ]);

            // 3. Smart Link (No Creation)
            // For PO: Optionally link SalesOrder
            if ($validated['document_type'] === 'PO') {
                if (! empty($validated['sales_order_id'])) {
                    // Link existing Sales Order
                    $so = SalesOrder::find($validated['sales_order_id']);
                    if ($so && empty($so->po_number)) {
                        $so->update([
                            'po_number' => $document->document_number,
                            'po_date' => $document->document_date,
                        ]);
                    }
                }
            }

            // For Invoice: Optionally sync with Invoices table
            if ($validated['document_type'] === 'INVOICE') {
                if (! empty($validated['invoice_id'])) {
                    // Already linked
                } else {
                    // Check if invoice exists by number
                    $existingInv = Invoice::where('tenant_id', $tenantId)
                        ->where('invoice_number', $document->document_number)
                        ->first();

                    if ($existingInv) {
                        $document->update(['invoice_id' => $existingInv->id]);
                    }
                }
            }

            // For LR: Optionally sync with CommercialShipment
            if ($validated['document_type'] === 'LR') {
                if (! empty($validated['commercial_shipment_id'])) {
                    $shipment = CommercialShipment::find($validated['commercial_shipment_id']);
                    if ($shipment && empty($shipment->proof_document_url)) {
                        $shipment->update(['proof_document_url' => $storedPath]);
                    }
                }
            }

            DB::commit();

            if ($request->wantsJson()) {
                return response()->json([
                    'success' => true,
                    'document' => $document->load(['customer', 'activeVersion']),
                    'message' => "✓ {$document->type_label} #{$document->document_number} saved successfully!",
                ]);
            }

            return back()->with('success', "✓ {$document->type_label} #{$document->document_number} uploaded and saved successfully!");

        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('Failed to save document: '.$e->getMessage(), [
                'exception' => $e,
                'request' => $request->except(['file']),
            ]);
            if ($request->wantsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to save document: '.$e->getMessage(),
                ], 500);
            }

            return back()->with('error', 'Failed to save document: '.$e->getMessage())->withInput();
        }
    }

    /**
     * Show document metadata, versions, and full relationship chain in JSON (for preview modal).
     */
    public function show($id)
    {
        $tenantId = TenantManager::getTenantId();
        $doc = BusinessDocument::where('tenant_id', $tenantId)
            ->with(['customer', 'salesOrder', 'shipment', 'invoice', 'activeVersion', 'versions.uploadedByUser', 'createdByUser'])
            ->findOrFail($id);

        return response()->json([
            'success' => true,
            'document' => [
                'id' => $doc->id,
                'document_type' => $doc->document_type,
                'type_label' => $doc->type_label,
                'type_badge_color' => $doc->type_badge_color,
                'document_number' => $doc->document_number,
                'document_date' => $doc->document_date?->format('d M Y'),
                'due_date' => $doc->due_date?->format('d M Y'),
                'status' => $doc->status,
                'total_amount' => (float) $doc->total_amount,
                'taxable_amount' => (float) $doc->taxable_amount,
                'tax_amount' => (float) $doc->tax_amount,
                'payment_terms' => $doc->payment_terms,
                'delivery_location' => $doc->delivery_location,
                'notes' => $doc->notes,
                'current_version' => $doc->current_version,
                'preview_url' => $doc->preview_url,
                'download_url' => $doc->download_url,
                'active_file_name' => $doc->activeVersion?->file_name ?? 'Document',
                'active_file_size' => $doc->activeVersion?->formatted_file_size ?? '0 B',
                'is_pdf' => $doc->activeVersion?->is_pdf ?? false,
                'is_image' => $doc->activeVersion?->is_image ?? false,
                'is_excel' => $doc->activeVersion?->is_excel ?? false,
                'connected_chain' => $doc->connected_chain,
                'versions' => $doc->versions->map(fn ($v) => [
                    'id' => $v->id,
                    'version_number' => $v->version_number,
                    'file_name' => $v->file_name,
                    'file_size' => $v->formatted_file_size,
                    'change_note' => $v->change_note,
                    'is_active' => $v->is_active,
                    'uploaded_by' => $v->uploadedByUser?->name ?? 'System',
                    'uploaded_at' => $v->created_at->format('d M Y, h:i A'),
                    'preview_url' => route('documents.preview', ['id' => $doc->id, 'v' => $v->version_number]),
                ]),
            ],
        ]);
    }

    /**
     * Preview Document File: Authenticated streaming with proper headers.
     */
    public function preview(Request $request, $id)
    {
        $tenantId = TenantManager::getTenantId();
        $doc = BusinessDocument::where('tenant_id', $tenantId)->findOrFail($id);

        $requestedVersion = $request->query('v');
        if ($requestedVersion) {
            $version = $doc->versions()->where('version_number', $requestedVersion)->firstOrFail();
        } else {
            $version = $doc->activeVersion ?: $doc->versions()->firstOrFail();
        }

        $path = $version->file_path;
        if (! Storage::disk('public')->exists($path)) {
            abort(404, 'Document file not found in storage.');
        }

        $filePath = Storage::disk('public')->path($path);
        $mime = $version->mime_type ?: Storage::disk('public')->mimeType($path);

        return response()->file($filePath, [
            'Content-Type' => $mime,
            'Content-Disposition' => 'inline; filename="'.addslashes($version->file_name).'"',
        ]);
    }

    /**
     * Download Document File.
     */
    public function download(Request $request, $id)
    {
        $tenantId = TenantManager::getTenantId();
        $doc = BusinessDocument::where('tenant_id', $tenantId)->findOrFail($id);

        $requestedVersion = $request->query('v');
        if ($requestedVersion) {
            $version = $doc->versions()->where('version_number', $requestedVersion)->firstOrFail();
        } else {
            $version = $doc->activeVersion ?: $doc->versions()->firstOrFail();
        }

        $path = $version->file_path;
        if (! Storage::disk('public')->exists($path)) {
            abort(404, 'Document file not found in storage.');
        }

        return Storage::disk('public')->download($path, $version->file_name);
    }

    /**
     * Upload a new revision/version for an existing document (Requirement 9: Document Versioning).
     */
    public function newVersion(Request $request, $id)
    {
        $tenantId = TenantManager::getTenantId();
        $doc = BusinessDocument::where('tenant_id', $tenantId)->findOrFail($id);

        $request->validate([
            'file' => 'required|file|mimes:pdf,jpg,jpeg,png,webp,xlsx,xls,csv|max:25600',
            'change_note' => 'nullable|string|max:255',
        ]);

        $file = $request->file('file');
        $originalName = $file->getClientOriginalName();
        $ext = $file->getClientOriginalExtension();
        $nextVersion = $doc->current_version + 1;
        $safeDocNum = Str::slug($doc->document_number, '_');
        $storageFilename = "doc_{$tenantId}_{$doc->document_type}_{$safeDocNum}_v{$nextVersion}_".time().'.'.$ext;

        $storedPath = $file->storeAs("documents/{$tenantId}", $storageFilename, 'public');

        try {
            DB::transaction(function () use ($doc, $file, $originalName, $storedPath, $nextVersion, $request) {
                // Set all existing versions to inactive
                $doc->versions()->update(['is_active' => false]);

                // Create new version
                DocumentVersion::create([
                    'document_id' => $doc->id,
                    'version_number' => $nextVersion,
                    'file_path' => $storedPath,
                    'file_name' => $originalName,
                    'file_size' => $file->getSize(),
                    'mime_type' => $file->getMimeType(),
                    'uploaded_by_user_id' => auth()->id(),
                    'change_note' => $request->input('change_note') ?: "Version {$nextVersion} uploaded",
                    'is_active' => true,
                ]);

                // Update document's current version
                $doc->update(['current_version' => $nextVersion]);
            });

            return back()->with('success', "✓ Version {$nextVersion} of {$doc->type_label} #{$doc->document_number} uploaded successfully!");
        } catch (\Throwable $e) {
            Log::error("Failed to upload new version for document #{$id}: ".$e->getMessage(), [
                'exception' => $e,
            ]);

            return back()->with('error', 'Failed to upload new version: '.$e->getMessage());
        }
    }

    /**
     * Delete a document and its stored files.
     */
    public function destroy($id)
    {
        $tenantId = TenantManager::getTenantId();
        $doc = BusinessDocument::where('tenant_id', $tenantId)->findOrFail($id);
        $num = $doc->document_number;

        try {
            DB::transaction(function () use ($doc) {
                // Remove files from storage
                foreach ($doc->versions as $v) {
                    if (Storage::disk('public')->exists($v->file_path)) {
                        Storage::disk('public')->delete($v->file_path);
                    }
                }
                $doc->delete();
            });

            return back()->with('success', "✓ Document #{$num} removed successfully.");
        } catch (\Throwable $e) {
            Log::error("Failed to delete document #{$id}: ".$e->getMessage(), [
                'exception' => $e,
            ]);

            return back()->with('error', 'Failed to delete document: '.$e->getMessage());
        }
    }

    /**
     * Real-time duplicate check API.
     */
    public function checkDuplicate(Request $request)
    {
        $tenantId = TenantManager::getTenantId();
        $type = $request->query('type', $request->query('document_type', 'PO'));
        $number = trim($request->query('number', $request->query('document_number', '')));
        $customerId = $request->query('customer_id') ? (int) $request->query('customer_id') : null;

        $dup = $this->parser->checkDuplicate($type, $number, $customerId, $tenantId);

        return response()->json([
            'is_duplicate' => ! empty($dup),
            'duplicate' => $dup,
            'document_number' => $number,
        ]);
    }
}
