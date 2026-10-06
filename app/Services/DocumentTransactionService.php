<?php

namespace App\Services;

use App\Models\BusinessDocument;
use App\Models\CommercialShipment;
use App\Models\Customer;
use App\Models\DocumentVersion;
use App\Models\Invoice;
use App\Models\SalesOrder;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class DocumentTransactionService
{
    /**
     * Check if a document already exists with the same type and number for the tenant/customer.
     */
    public function checkDuplicate(string $documentType, string $documentNumber, ?int $customerId, int $tenantId, ?int $excludeDocId = null): ?BusinessDocument
    {
        $cleanNumber = strtoupper(trim($documentNumber));
        if (empty($cleanNumber)) {
            return null;
        }

        $query = BusinessDocument::where('tenant_id', $tenantId)
            ->where('document_type', strtoupper($documentType))
            ->where('document_number', $cleanNumber);

        if ($customerId) {
            $query->where('customer_id', $customerId);
        }

        if ($excludeDocId) {
            $query->where('id', '!=', $excludeDocId);
        }

        return $query->first();
    }

    /**
     * Attach Customer PO Document directly during Sales Order creation.
     */
    public function attachCustomerPo(SalesOrder $order, UploadedFile $file, ?string $notes = null): BusinessDocument
    {
        $tenantId = $order->tenant_id ?: TenantManager::getTenantId();
        $poNumber = strtoupper(trim($order->po_number ?: $order->order_number));

        // Check for accidental duplicate upload
        $existing = $this->checkDuplicate('PO', $poNumber, $order->customer_id, $tenantId);
        if ($existing && $existing->sales_order_id && $existing->sales_order_id !== $order->id) {
            throw ValidationException::withMessages([
                'po_document' => "A Customer PO document for PO #{$poNumber} is already registered to Sales Order #{$existing->salesOrder?->order_number}.",
            ]);
        }

        $originalName = $file->getClientOriginalName();
        $ext = $file->getClientOriginalExtension();
        $safePoNum = Str::slug($poNumber, '_');
        $storageFilename = "doc_{$tenantId}_PO_{$safePoNum}_".time().'.'.$ext;

        $storedPath = $file->storeAs("documents/{$tenantId}", $storageFilename, 'public');

        try {
            $document = BusinessDocument::create([
                'tenant_id' => $tenantId,
                'document_type' => 'PO',
                'document_number' => $poNumber,
                'document_date' => $order->order_date ?: Carbon::now(),
                'due_date' => $order->expected_dispatch_date,
                'customer_id' => $order->customer_id,
                'sales_order_id' => $order->id,
                'total_amount' => (float) $order->total_amount,
                'taxable_amount' => (float) $order->subtotal,
                'tax_amount' => (float) $order->tax_amount,
                'currency' => 'INR',
                'payment_terms' => $order->payment_terms ?: '30 Days Credit',
                'delivery_location' => $order->customer?->city ? "{$order->customer->city}, {$order->customer->state}" : null,
                'status' => 'VERIFIED',
                'notes' => $notes ?: "Customer PO uploaded during creation of Sales Order {$order->order_number}",
                'extracted_metadata' => [
                    'source' => 'SALES_ORDER_CREATION',
                    'order_number' => $order->order_number,
                    'original_file_name' => $originalName,
                ],
                'current_version' => 1,
                'created_by_user_id' => auth()->id(),
            ]);

            DocumentVersion::create([
                'document_id' => $document->id,
                'version_number' => 1,
                'file_path' => $storedPath,
                'file_name' => $originalName,
                'file_size' => $file->getSize(),
                'mime_type' => $file->getMimeType(),
                'uploaded_by_user_id' => auth()->id(),
                'change_note' => "Initial Customer PO upload for Sales Order {$order->order_number}",
                'is_active' => true,
            ]);

            return $document;
        } catch (\Throwable $e) {
            Storage::disk('public')->delete($storedPath);
            throw $e;
        }
    }

    /**
     * Attach Transport / LR Document directly during Shipment creation.
     */
    public function attachShipmentLr(CommercialShipment $shipment, UploadedFile $file, ?string $notes = null): BusinessDocument
    {
        $tenantId = $shipment->tenant_id ?: TenantManager::getTenantId();
        $lrNumber = strtoupper(trim($shipment->lr_number));

        // Resolve primary customer and order from shipment items or relations
        $distinctOrders = $shipment->getDistinctOrders();
        $primaryOrder = $distinctOrders->first() ?: $shipment->salesOrder;
        $customerId = $primaryOrder?->customer_id;

        // Check for accidental duplicate upload
        $existing = $this->checkDuplicate('LR', $lrNumber, $customerId, $tenantId);
        if ($existing && $existing->commercial_shipment_id && $existing->commercial_shipment_id !== $shipment->id) {
            throw ValidationException::withMessages([
                'lr_document' => "An LR document for LR #{$lrNumber} is already registered to Shipment #{$existing->shipment?->shipment_number}.",
            ]);
        }

        $originalName = $file->getClientOriginalName();
        $ext = $file->getClientOriginalExtension();
        $safeLrNum = Str::slug($lrNumber, '_');
        $storageFilename = "doc_{$tenantId}_LR_{$safeLrNum}_".time().'.'.$ext;

        $storedPath = $file->storeAs("documents/{$tenantId}", $storageFilename, 'public');

        try {
            $document = BusinessDocument::create([
                'tenant_id' => $tenantId,
                'document_type' => 'LR',
                'document_number' => $lrNumber,
                'document_date' => $shipment->shipment_date ?: Carbon::now(),
                'customer_id' => $customerId,
                'sales_order_id' => $primaryOrder?->id,
                'commercial_shipment_id' => $shipment->id,
                'total_amount' => (float) ($shipment->freight_amount ?: 0),
                'currency' => 'INR',
                'delivery_location' => $shipment->destination,
                'status' => 'VERIFIED',
                'notes' => $notes ?: "Transport LR uploaded during creation of Shipment {$shipment->shipment_number} ({$shipment->transporter})",
                'extracted_metadata' => [
                    'source' => 'SHIPMENT_CREATION',
                    'shipment_number' => $shipment->shipment_number,
                    'transporter' => $shipment->transporter,
                    'vehicle_number' => $shipment->vehicle_number,
                    'original_file_name' => $originalName,
                ],
                'current_version' => 1,
                'created_by_user_id' => auth()->id(),
            ]);

            DocumentVersion::create([
                'document_id' => $document->id,
                'version_number' => 1,
                'file_path' => $storedPath,
                'file_name' => $originalName,
                'file_size' => $file->getSize(),
                'mime_type' => $file->getMimeType(),
                'uploaded_by_user_id' => auth()->id(),
                'change_note' => "Initial LR consignment upload for Shipment {$shipment->shipment_number}",
                'is_active' => true,
            ]);

            // Keep proof_document_url on shipment in sync
            $shipment->update(['proof_document_url' => $storedPath]);

            return $document;
        } catch (\Throwable $e) {
            Storage::disk('public')->delete($storedPath);
            throw $e;
        }
    }

    /**
     * Attach Supporting Document directly during Invoice generation.
     */
    public function attachInvoiceSupportingDoc(Invoice $invoice, UploadedFile $file, ?string $notes = null): BusinessDocument
    {
        $tenantId = $invoice->tenant_id ?: TenantManager::getTenantId();
        $invNumber = strtoupper(trim($invoice->invoice_number));

        $originalName = $file->getClientOriginalName();
        $ext = $file->getClientOriginalExtension();
        $safeInvNum = Str::slug($invNumber, '_');
        $storageFilename = "doc_{$tenantId}_INV_{$safeInvNum}_supp_".time().'.'.$ext;

        $storedPath = $file->storeAs("documents/{$tenantId}", $storageFilename, 'public');

        try {
            $document = BusinessDocument::create([
                'tenant_id' => $tenantId,
                'document_type' => 'INVOICE',
                'document_number' => $invNumber.'-SUPP',
                'document_date' => $invoice->invoice_date ?: Carbon::now(),
                'due_date' => $invoice->due_date,
                'customer_id' => $invoice->customer_id,
                'sales_order_id' => $invoice->sales_order_id,
                'commercial_shipment_id' => $invoice->commercial_shipment_id,
                'invoice_id' => $invoice->id,
                'total_amount' => (float) $invoice->total_amount,
                'taxable_amount' => (float) $invoice->subtotal,
                'tax_amount' => (float) $invoice->gst_amount,
                'currency' => 'INR',
                'payment_terms' => $invoice->customer?->payment_terms_days ? "{$invoice->customer->payment_terms_days} Days Credit" : '30 Days Credit',
                'status' => $invoice->status,
                'notes' => $notes ?: "Supporting document uploaded with Tax Invoice {$invoice->invoice_number}",
                'extracted_metadata' => [
                    'source' => 'INVOICE_SUPPORTING_UPLOAD',
                    'invoice_number' => $invoice->invoice_number,
                    'original_file_name' => $originalName,
                ],
                'current_version' => 1,
                'created_by_user_id' => auth()->id(),
            ]);

            DocumentVersion::create([
                'document_id' => $document->id,
                'version_number' => 1,
                'file_path' => $storedPath,
                'file_name' => $originalName,
                'file_size' => $file->getSize(),
                'mime_type' => $file->getMimeType(),
                'uploaded_by_user_id' => auth()->id(),
                'change_note' => "Supporting document for Tax Invoice {$invoice->invoice_number}",
                'is_active' => true,
            ]);

            return $document;
        } catch (\Throwable $e) {
            Storage::disk('public')->delete($storedPath);
            throw $e;
        }
    }

    /**
     * Automatically render, store, and link the ERP-generated final Invoice PDF in Document Centre.
     */
    public function generateAndStoreInvoicePdf(Invoice $invoice): BusinessDocument
    {
        $invoice->loadMissing(['customer', 'items.product', 'items.salesOrderItem', 'salesOrder', 'commercialShipment']);
        $tenant = tenant() ?: TenantManager::getTenant();
        $tenantId = $tenant?->id ?: 1;
        $invNumber = strtoupper(trim($invoice->invoice_number));

        // Render PDF using DomPDF
        $pdf = Pdf::loadView('invoices.pdf', compact('invoice', 'tenant'));
        $pdf->setPaper('A4', 'portrait');
        $pdfContent = $pdf->output();

        $safeInvNum = Str::slug($invNumber, '_');
        $storageFilename = "doc_{$tenantId}_INVOICE_{$safeInvNum}_official.pdf";
        $storedPath = "documents/{$tenantId}/{$storageFilename}";

        Storage::disk('public')->put($storedPath, $pdfContent);
        $fileSize = strlen($pdfContent);
        $fileName = "Tax_Invoice_{$invNumber}.pdf";

        // Check if an official generated PDF document record already exists for this invoice
        $existingDoc = BusinessDocument::where('tenant_id', $tenantId)
            ->where('invoice_id', $invoice->id)
            ->where('document_number', $invNumber)
            ->first();

        if ($existingDoc) {
            $nextVersion = $existingDoc->current_version + 1;
            $existingDoc->versions()->update(['is_active' => false]);

            DocumentVersion::create([
                'document_id' => $existingDoc->id,
                'version_number' => $nextVersion,
                'file_path' => $storedPath,
                'file_name' => $fileName,
                'file_size' => $fileSize,
                'mime_type' => 'application/pdf',
                'uploaded_by_user_id' => auth()->id(),
                'change_note' => "Regenerated Tax Invoice PDF v{$nextVersion}",
                'is_active' => true,
            ]);

            $existingDoc->update([
                'current_version' => $nextVersion,
                'total_amount' => (float) $invoice->total_amount,
                'taxable_amount' => (float) $invoice->subtotal,
                'tax_amount' => (float) $invoice->gst_amount,
                'status' => $invoice->status,
            ]);

            return $existingDoc;
        }

        $document = BusinessDocument::create([
            'tenant_id' => $tenantId,
            'document_type' => 'INVOICE',
            'document_number' => $invNumber,
            'document_date' => $invoice->invoice_date ?: Carbon::now(),
            'due_date' => $invoice->due_date,
            'customer_id' => $invoice->customer_id,
            'sales_order_id' => $invoice->sales_order_id,
            'commercial_shipment_id' => $invoice->commercial_shipment_id,
            'invoice_id' => $invoice->id,
            'total_amount' => (float) $invoice->total_amount,
            'taxable_amount' => (float) $invoice->subtotal,
            'tax_amount' => (float) $invoice->gst_amount,
            'currency' => 'INR',
            'payment_terms' => $invoice->customer?->payment_terms_days ? "{$invoice->customer->payment_terms_days} Days Credit" : '30 Days Credit',
            'delivery_location' => $invoice->customer?->city ? "{$invoice->customer->city}, {$invoice->customer->state}" : null,
            'status' => $invoice->status,
            'notes' => 'Official ERP-generated Tax Invoice PDF with GST calculation breakdown.',
            'extracted_metadata' => [
                'source' => 'ERP_GENERATED_PDF',
                'invoice_number' => $invoice->invoice_number,
                'generated_at' => Carbon::now()->toIso8601String(),
            ],
            'current_version' => 1,
            'created_by_user_id' => auth()->id(),
        ]);

        DocumentVersion::create([
            'document_id' => $document->id,
            'version_number' => 1,
            'file_path' => $storedPath,
            'file_name' => $fileName,
            'file_size' => $fileSize,
            'mime_type' => 'application/pdf',
            'uploaded_by_user_id' => auth()->id(),
            'change_note' => 'Official ERP-generated Tax Invoice PDF',
            'is_active' => true,
        ]);

        return $document;
    }
}
