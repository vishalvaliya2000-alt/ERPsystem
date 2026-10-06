<?php

namespace App\Http\Controllers;

use App\Models\BusinessDocument;
use App\Models\CommercialShipment;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Product;
use App\Models\SalesOrder;
use Illuminate\Http\Request;

class SearchController extends Controller
{
    public function search(Request $request)
    {
        $q = trim($request->query('q', ''));
        if (strlen($q) < 2) {
            return response()->json(['results' => []]);
        }

        $results = [];

        // Customers
        foreach (Customer::where('company_name', 'like', "%{$q}%")->orWhere('customer_code', 'like', "%{$q}%")->take(4)->get() as $c) {
            $results[] = [
                'type' => 'customer',
                'title' => $c->company_name,
                'subtitle' => "Code: {$c->customer_code} · {$c->city}, {$c->state} · Contact: {$c->primary_contact_person}",
                'url' => route('customers.show', $c->id),
                'tag' => 'Customer',
            ];
        }

        // Products
        foreach (Product::where('product_name', 'like', "%{$q}%")->orWhere('product_code', 'like', "%{$q}%")->take(4)->get() as $p) {
            $results[] = [
                'type' => 'product',
                'title' => $p->product_name,
                'subtitle' => "SKU: {$p->product_code} · Rate: ₹{$p->standard_rate}/kg · HSN: {$p->hsn_code}",
                'url' => route('products.index', ['q' => $p->product_code]),
                'tag' => 'Product',
            ];
        }

        // Sales Orders
        foreach (SalesOrder::with('customer')->where('order_number', 'like', "%{$q}%")->orWhere('po_number', 'like', "%{$q}%")->take(4)->get() as $o) {
            $results[] = [
                'type' => 'order',
                'title' => "Order {$o->order_number}",
                'subtitle' => 'Customer: '.($o->customer->company_name ?? 'N/A').' · Total: '.formatINR($o->total_amount),
                'url' => route('orders.index'),
                'tag' => 'Sales Order',
            ];
        }

        // Invoices
        foreach (Invoice::with('customer')->where('invoice_number', 'like', "%{$q}%")->take(4)->get() as $inv) {
            $results[] = [
                'type' => 'invoice',
                'title' => "Invoice {$inv->invoice_number}",
                'subtitle' => 'Customer: '.($inv->customer->company_name ?? 'N/A').' · Balance: '.formatINR($inv->balance_due),
                'url' => route('invoices.index'),
                'tag' => 'Invoice',
            ];
        }

        // Shipments
        foreach (CommercialShipment::with('salesOrder.customer')->where('shipment_number', 'like', "%{$q}%")->orWhere('lr_number', 'like', "%{$q}%")->take(4)->get() as $s) {
            $results[] = [
                'type' => 'shipment',
                'title' => "Shipment {$s->shipment_number} (LR: {$s->lr_number})",
                'subtitle' => "Transporter: {$s->transporter} · Order: ".($s->salesOrder->order_number ?? 'N/A'),
                'url' => route('shipments.index'),
                'tag' => 'LR Tracking',
            ];
        }

        // Documents (PO, Invoice, LR)
        foreach (BusinessDocument::with('customer', 'activeVersion')
            ->where('document_number', 'like', "%{$q}%")
            ->orWhere('notes', 'like', "%{$q}%")
            ->orWhereHas('versions', fn ($vq) => $vq->where('file_name', 'like', "%{$q}%"))
            ->take(4)->get() as $doc) {
            $results[] = [
                'type' => 'document',
                'title' => "{$doc->type_label} {$doc->document_number}",
                'subtitle' => 'Customer: '.($doc->customer->company_name ?? 'N/A').' · Amount: '.formatINR($doc->total_amount)." · Status: {$doc->status}",
                'url' => route('documents.preview', $doc->id),
                'tag' => 'Document ('.$doc->document_type.')',
            ];
        }

        return response()->json(['results' => $results]);
    }
}
