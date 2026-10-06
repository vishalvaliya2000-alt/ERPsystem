<?php

namespace Database\Seeders;

use App\Models\BusinessDocument;
use App\Models\CommercialShipment;
use App\Models\DocumentVersion;
use App\Models\Invoice;
use App\Models\SalesOrder;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class DocumentSyncSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Sync Existing LRs from CommercialShipment
        $shipments = CommercialShipment::withoutGlobalScopes()
            ->whereNotNull('proof_document_url')
            ->get();

        foreach ($shipments as $s) {
            $existing = BusinessDocument::withoutGlobalScopes()
                ->where('tenant_id', $s->tenant_id)
                ->where('commercial_shipment_id', $s->id)
                ->first();

            if ($existing) {
                continue;
            }

            $order = $s->salesOrder ?: $s->getDistinctOrders()->first();
            $customer = $order?->customer;
            $fileName = basename($s->proof_document_url);

            $doc = BusinessDocument::create([
                'tenant_id' => $s->tenant_id,
                'document_type' => 'LR',
                'document_number' => $s->lr_number ?: $s->shipment_number,
                'document_date' => $s->shipment_date ?: Carbon::now(),
                'customer_id' => $customer?->id,
                'sales_order_id' => $order?->id,
                'commercial_shipment_id' => $s->id,
                'total_amount' => $s->total_consignment_value,
                'taxable_amount' => $s->material_value,
                'tax_amount' => 0,
                'currency' => 'INR',
                'payment_terms' => $s->freight_payment_type ?: 'TO_PAY',
                'delivery_location' => $s->destination ?: 'Customer Godown',
                'status' => $s->status === 'DELIVERED' ? 'VERIFIED' : 'ACTIVE',
                'notes' => "Road consignment transported via {$s->transporter}.",
                'current_version' => 1,
            ]);

            DocumentVersion::create([
                'document_id' => $doc->id,
                'version_number' => 1,
                'file_path' => $s->proof_document_url,
                'file_name' => $fileName,
                'file_size' => 142500,
                'mime_type' => str_ends_with(strtolower($fileName), '.pdf') ? 'application/pdf' : 'image/jpeg',
                'change_note' => 'Original LR consignment bill',
                'is_active' => true,
            ]);
        }

        // 2. Register initial PO documents for existing Sales Orders
        $orders = SalesOrder::withoutGlobalScopes()->with(['customer', 'items'])->get();
        foreach ($orders as $o) {
            $existing = BusinessDocument::withoutGlobalScopes()
                ->where('tenant_id', $o->tenant_id)
                ->where('sales_order_id', $o->id)
                ->where('document_type', 'PO')
                ->first();

            if ($existing) {
                continue;
            }

            $poNum = $o->po_number ?: ('PO-'.strtoupper(substr(preg_replace('/[^A-Za-z]/', '', $o->customer?->company_name ?? 'CUST'), 0, 3)).'-'.rand(100, 999));
            $fileName = "PO_{$poNum}.pdf";

            $doc = BusinessDocument::create([
                'tenant_id' => $o->tenant_id,
                'document_type' => 'PO',
                'document_number' => $poNum,
                'document_date' => $o->po_date ?: ($o->order_date ?: Carbon::now()),
                'customer_id' => $o->customer_id,
                'sales_order_id' => $o->id,
                'total_amount' => $o->total_amount,
                'taxable_amount' => $o->subtotal,
                'tax_amount' => $o->tax_amount,
                'currency' => 'INR',
                'payment_terms' => $o->payment_terms ?: '30 Days Credit',
                'delivery_location' => $o->customer ? ($o->customer->city.', '.$o->customer->state) : 'Factory Godown',
                'status' => 'VERIFIED',
                'notes' => "Customer Purchase Order linked to contract {$o->order_number}.",
                'current_version' => 1,
            ]);

            DocumentVersion::create([
                'document_id' => $doc->id,
                'version_number' => 1,
                'file_path' => "documents/{$o->tenant_id}/sample_po_{$o->id}.pdf",
                'file_name' => $fileName,
                'file_size' => 98400,
                'mime_type' => 'application/pdf',
                'change_note' => 'Initial buyer contract copy',
                'is_active' => true,
            ]);
        }

        // 3. Register initial Tax Invoices
        $invoices = Invoice::withoutGlobalScopes()->with('customer')->get();
        foreach ($invoices as $inv) {
            $existing = BusinessDocument::withoutGlobalScopes()
                ->where('tenant_id', $inv->tenant_id)
                ->where('invoice_id', $inv->id)
                ->first();

            if ($existing) {
                continue;
            }

            $fileName = "Invoice_{$inv->invoice_number}.pdf";

            $doc = BusinessDocument::create([
                'tenant_id' => $inv->tenant_id,
                'document_type' => 'INVOICE',
                'document_number' => $inv->invoice_number,
                'document_date' => $inv->invoice_date ?: Carbon::now(),
                'due_date' => $inv->due_date ?: Carbon::now()->addDays(30),
                'customer_id' => $inv->customer_id,
                'sales_order_id' => $inv->sales_order_id,
                'commercial_shipment_id' => $inv->commercial_shipment_id,
                'invoice_id' => $inv->id,
                'total_amount' => $inv->total_amount,
                'taxable_amount' => $inv->subtotal,
                'tax_amount' => $inv->gst_amount,
                'currency' => 'INR',
                'payment_terms' => '30 Days Credit',
                'delivery_location' => $inv->customer ? ($inv->customer->city.', '.$inv->customer->state) : null,
                'status' => $inv->status === 'PAID' ? 'PAID' : ($inv->status === 'OVERDUE' ? 'OVERDUE' : 'VERIFIED'),
                'notes' => "Tax Invoice issued against order {$inv->display_po_numbers}.",
                'current_version' => 1,
            ]);

            DocumentVersion::create([
                'document_id' => $doc->id,
                'version_number' => 1,
                'file_path' => "documents/{$inv->tenant_id}/sample_inv_{$inv->id}.pdf",
                'file_name' => $fileName,
                'file_size' => 112000,
                'mime_type' => 'application/pdf',
                'change_note' => 'Original signed tax invoice',
                'is_active' => true,
            ]);
        }
    }
}
