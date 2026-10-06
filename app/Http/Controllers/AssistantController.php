<?php

namespace App\Http\Controllers;

use App\Models\CommercialShipment;
use App\Models\CommercialShipmentItem;
use App\Models\Customer;
use App\Models\FollowupTask;
use App\Models\InventoryStock;
use App\Models\Invoice;
use App\Models\Product;
use App\Models\ProductionBatch;
use App\Models\SalesOrder;
use App\Models\Sample;
use App\Services\TenantManager;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class AssistantController extends Controller
{
    public function index()
    {
        return view('assistant.index');
    }

    public function query(Request $request)
    {
        $rawQuery = trim($request->input('query', ''));
        $q = strtolower($rawQuery);

        if (empty($q)) {
            return response()->json([
                'title' => 'AI Copilot Ready',
                'answer' => 'Please ask any question regarding your sales orders, customer dispatches, pending shipments, inventory stocks, or outstanding ledger.',
                'action_url' => null,
            ]);
        }

        try {
            // ==========================================
            // 1. ENTITY & TEMPORAL EXTRACTION
            // ==========================================

            $isLatestOrLast = str_contains($q, 'last') || str_contains($q, 'latest') || str_contains($q, 'recent') || str_contains($q, 'most recent') || str_contains($q, 'previous');

            // A. Match Customer
            $matchedCustomer = null;
            $allCustomers = Customer::with(['salesOrders.items.product', 'invoices', 'samples'])->get();
            foreach ($allCustomers as $cust) {
                $cName = strtolower($cust->company_name);
                $cCode = strtolower($cust->customer_code);
                $firstWord = explode(' ', $cName)[0];

                if (str_contains($q, $cName) || str_contains($q, $cCode) || (strlen($firstWord) > 2 && str_contains($q, $firstWord))) {
                    $matchedCustomer = $cust;
                    break;
                }
            }

            // B. Match Product
            $matchedProduct = null;
            $matchedCategory = null;
            $allProducts = Product::with('category')->get();

            // Sort products by name length descending so "Toasted White Onion Powder" matches before "White Onion Powder"
            $sortedProducts = $allProducts->sortByDesc(fn ($p) => strlen($p->product_name));
            foreach ($sortedProducts as $prod) {
                $pName = strtolower($prod->product_name);
                if (str_contains($q, $pName)) {
                    $matchedProduct = $prod;
                    break;
                }
            }

            if (! $matchedProduct) {
                if (str_contains($q, 'garlic powder')) {
                    $matchedProduct = $allProducts->firstWhere('product_name', 'Garlic Powder');
                } elseif (str_contains($q, 'white onion powder')) {
                    $matchedProduct = $allProducts->firstWhere('product_name', 'White Onion Powder');
                } elseif (str_contains($q, 'toasted') && str_contains($q, 'onion')) {
                    $matchedProduct = $allProducts->firstWhere('product_name', 'Toasted White Onion Powder');
                } elseif (str_contains($q, 'pink onion') || str_contains($q, 'red onion')) {
                    $matchedProduct = $allProducts->firstWhere('product_name', 'Pink Onion Powder') ?? $allProducts->firstWhere('product_name', 'Pink Onion Flakes');
                } elseif (str_contains($q, 'garlic flakes')) {
                    $matchedProduct = $allProducts->firstWhere('product_name', 'Garlic Flakes');
                } elseif (str_contains($q, 'garlic')) {
                    $matchedCategory = 'garlic';
                } elseif (str_contains($q, 'onion')) {
                    $matchedCategory = 'onion';
                }
            }

            // C. Match Specific PO / Order
            $matchedOrder = null;
            if (preg_match('/(po[- ]?[0-9a-z\/]+|so[- ]?[0-9a-z\/]+)/i', $q, $m)) {
                $cleanPo = strtoupper(str_replace(' ', '', $m[1]));
                $matchedOrder = SalesOrder::with(['customer', 'items.product', 'shipments', 'invoices'])
                    ->where('order_number', 'like', "%{$cleanPo}%")
                    ->orWhere('po_number', 'like', "%{$cleanPo}%")
                    ->first();
            }

            // D. Match Specific Invoice Number
            $matchedInvoice = null;
            if (preg_match('/(inv[- ]?[0-9]+)/i', $q, $m)) {
                $cleanInv = strtoupper(str_replace(' ', '', $m[1]));
                $matchedInvoice = Invoice::with(['customer', 'items.product', 'receipts'])
                    ->where('invoice_number', 'like', "%{$cleanInv}%")
                    ->first();
            }

            // E. Match Specific LR / Bilty Number
            $matchedShipment = null;
            if (preg_match('/(lr[- ]?[0-9]+|[0-9]{8,12})/i', $q, $m)) {
                $cleanLr = preg_replace('/[^0-9]/', '', $m[1]);
                if (! empty($cleanLr)) {
                    $matchedShipment = CommercialShipment::with(['salesOrder.customer', 'items.salesOrderItem.product', 'items.salesOrderItem.salesOrder.customer', 'invoices'])
                        ->where('lr_number', 'like', "%{$cleanLr}%")
                        ->first();
                }
            }

            // ==========================================
            // 2. INTENT ROUTING
            // ==========================================

            // --- INTENT 1: Specific LR / Consignment Tracking Query ---
            if ($matchedShipment) {
                $custNames = $matchedShipment->customer_names;
                $itemsSummary = [];
                foreach ($matchedShipment->items as $it) {
                    $pName = $it->salesOrderItem?->product?->product_name ?? 'Product';
                    $itemsSummary[] = "{$pName}: ".number_format($it->quantity)." KG @ ₹{$it->unit_value} = ".formatINR($it->total_value);
                }

                return response()->json([
                    'title' => "Consignment Tracking: LR {$matchedShipment->lr_number}",
                    'answer' => "LR {$matchedShipment->lr_number} dispatched via {$matchedShipment->transporter} on ".($matchedShipment->shipment_date?->format('d M Y') ?? 'N/A')." for {$custNames}. Status: {$matchedShipment->status}.",
                    'details' => [
                        'Consignment LR' => "LR {$matchedShipment->lr_number} · {$matchedShipment->transporter}",
                        'Dispatch Date' => $matchedShipment->shipment_date?->format('d M Y') ?? 'N/A',
                        'Customer' => $custNames,
                        'Destination' => $matchedShipment->destination ?: 'Customer Godown',
                        'Vehicle' => $matchedShipment->vehicle_number ?: 'N/A',
                        'Freight Terms' => $matchedShipment->freight_payment_type.($matchedShipment->freight_amount > 0 ? ' ('.formatINR($matchedShipment->freight_amount).')' : ''),
                        'Dispatched Goods' => implode(' | ', $itemsSummary),
                        'Total Material Value' => formatINR($matchedShipment->material_value),
                    ],
                    'action_url' => route('shipments.index'),
                    'action_label' => 'Open Shipments Module',
                ]);
            }

            // --- INTENT 2: Specific Invoice Query ---
            if ($matchedInvoice) {
                $statusText = $matchedInvoice->balance_due <= 0 ? 'Fully Paid & Settled ✓' : 'Payment Pending ('.formatINR($matchedInvoice->balance_due).' Due)';

                return response()->json([
                    'title' => "Tax Invoice: {$matchedInvoice->invoice_number}",
                    'answer' => "Invoice {$matchedInvoice->invoice_number} dated ".$matchedInvoice->invoice_date->format('d M Y')." for {$matchedInvoice->customer->company_name}. Total: ".formatINR($matchedInvoice->total_amount).". Status: {$statusText}.",
                    'details' => [
                        'Customer' => $matchedInvoice->customer->company_name,
                        'Total Billed' => formatINR($matchedInvoice->total_amount),
                        'Received Amount' => formatINR($matchedInvoice->amount_received),
                        'Balance Due' => formatINR($matchedInvoice->balance_due),
                        'Due Date' => $matchedInvoice->due_date ? $matchedInvoice->due_date->format('d M Y') : '—',
                    ],
                    'action_url' => route('invoices.index'),
                    'action_label' => 'View Invoices',
                ]);
            }

            // --- INTENT 3: Specific Sales Order / PO Query ---
            if ($matchedOrder) {
                $custName = $matchedOrder->customer->company_name ?? 'Client';
                $lines = [];
                foreach ($matchedOrder->items as $it) {
                    $pName = $it->product->product_name ?? 'Product';
                    $lines[] = "{$pName}: ".number_format($it->order_qty).' KG (Shipped: '.number_format($it->shipped_qty).' KG | Bal: '.number_format($it->balance_qty).' KG)';
                }

                return response()->json([
                    'title' => "Sales Contract: {$matchedOrder->order_number} ({$custName})",
                    'answer' => "Order {$matchedOrder->order_number} for {$custName} has a total contracted value of ".formatINR($matchedOrder->total_amount).' ('.number_format($matchedOrder->total_quantity).' KG). Shipped: '.number_format($matchedOrder->shipped_quantity).' KG ('.number_format($matchedOrder->fulfillment_percentage, 1).'%). Balance: '.number_format($matchedOrder->balance_quantity).' KG.',
                    'details' => [
                        'Customer' => $custName,
                        'Contract Date' => $matchedOrder->order_date?->format('d M Y') ?? '—',
                        'Total Contract' => formatINR($matchedOrder->total_amount).' ('.number_format($matchedOrder->total_quantity / 1000, 2).' MT)',
                        'Shipped' => number_format($matchedOrder->shipped_quantity / 1000, 2).' MT ('.number_format($matchedOrder->fulfillment_percentage, 1).'%)',
                        'Pending Balance' => number_format($matchedOrder->balance_quantity / 1000, 2).' MT ('.number_format($matchedOrder->balance_quantity).' KG)',
                        'Product Lines' => implode(' | ', $lines),
                    ],
                    'action_url' => route('orders.index'),
                    'action_label' => 'Open Sales Orders',
                ]);
            }

            // --- INTENT 4: "Last / Latest / Recent Dispatch" Query ---
            $isShipmentQuery = str_contains($q, 'ship') || str_contains($q, 'dispatch') || str_contains($q, 'deliver') || str_contains($q, 'consignment') || str_contains($q, 'sent') || str_contains($q, 'lr') || str_contains($q, 'bilty') || str_contains($q, 'how much') || str_contains($q, 'how many kg') || str_contains($q, 'how much material');

            if ($isLatestOrLast && ($isShipmentQuery || $matchedProduct || $matchedCustomer)) {
                $query = CommercialShipmentItem::join('commercial_shipments', 'commercial_shipments.id', '=', 'commercial_shipment_items.commercial_shipment_id')
                    ->select('commercial_shipment_items.*')
                    ->with(['shipment', 'salesOrderItem.product', 'salesOrderItem.salesOrder.customer'])
                    ->orderByDesc('commercial_shipments.shipment_date')
                    ->orderByDesc('commercial_shipments.id');

                if ($matchedCustomer) {
                    $query->whereHas('salesOrderItem.salesOrder', fn ($sq) => $sq->where('customer_id', $matchedCustomer->id));
                }

                if ($matchedProduct) {
                    $query->whereHas('salesOrderItem', fn ($sq) => $sq->where('product_id', $matchedProduct->id));
                } elseif ($matchedCategory) {
                    $query->whereHas('salesOrderItem.product.category', fn ($sq) => $sq->where('slug', $matchedCategory));
                }

                $latestItem = $query->first();

                if ($latestItem) {
                    $shp = $latestItem->shipment;
                    $so = $latestItem->salesOrderItem?->salesOrder;
                    $cust = $so?->customer ?? $shp?->salesOrder?->customer;
                    $prod = $latestItem->salesOrderItem?->product;
                    $dateStr = $shp?->shipment_date ? $shp->shipment_date->format('d M Y') : 'Recent';
                    $pName = $prod?->product_name ?? 'Dehydrated Material';
                    $cName = $cust?->company_name ?? 'Client';

                    return response()->json([
                        'title' => "Latest Dispatch: {$pName} (LR {$shp->lr_number})",
                        'answer' => "The last dispatch of {$pName} was on {$dateStr} to {$cName} under LR {$shp->lr_number} via {$shp->transporter}. Dispatched: ".number_format($latestItem->quantity).' KG ('.number_format($latestItem->quantity / 1000, 2)." MT) @ ₹{$latestItem->unit_value}/KG, with a total value of ".formatINR($latestItem->total_value)." against PO {$so?->order_number}.",
                        'details' => [
                            'Consignment LR' => "LR {$shp->lr_number} · {$shp->transporter}",
                            'Dispatch Date' => $dateStr,
                            'Customer' => "{$cName} (".($cust?->city ?? 'Godown').', '.($cust?->state ?? '').')',
                            'Product' => "{$pName}".($prod?->sku ? " (SKU: {$prod->sku})" : ''),
                            'Dispatched Weight' => number_format($latestItem->quantity).' KG ('.number_format($latestItem->quantity / 1000, 2).' MT)',
                            'Agreed Rate' => '₹'.number_format($latestItem->unit_value, 2).' / KG',
                            'Consignment Value' => formatINR($latestItem->total_value),
                            'Purchase Order (PO)' => $so?->order_number ?? 'PO',
                            'Destination' => $shp->destination ?: ($cust?->city ?? 'Customer Godown'),
                            'Freight Terms' => $shp->freight_payment_type.($shp->freight_amount > 0 ? ' ('.formatINR($shp->freight_amount).')' : ''),
                            'Delivery Status' => $shp->status ?: 'DISPATCHED',
                        ],
                        'action_url' => route('shipments.index'),
                        'action_label' => "Open LR {$shp->lr_number} in Shipments",
                    ]);
                }
            }

            // --- INTENT 5: Cumulative Shipment / Dispatch Volume Query ---
            if ($isShipmentQuery || ($matchedCustomer && (str_contains($q, 'powder') || str_contains($q, 'flakes') || str_contains($q, 'minced') || str_contains($q, 'chopped')))) {
                $queryBuilder = CommercialShipmentItem::join('commercial_shipments', 'commercial_shipments.id', '=', 'commercial_shipment_items.commercial_shipment_id')
                    ->select('commercial_shipment_items.*')
                    ->with(['shipment', 'salesOrderItem.product', 'salesOrderItem.salesOrder.customer'])
                    ->orderByDesc('commercial_shipments.shipment_date')
                    ->orderByDesc('commercial_shipments.id');

                if ($matchedCustomer) {
                    $queryBuilder->whereHas('salesOrderItem.salesOrder', function ($sq) use ($matchedCustomer) {
                        $sq->where('customer_id', $matchedCustomer->id);
                    });
                }

                if ($matchedProduct) {
                    $queryBuilder->whereHas('salesOrderItem', function ($sq) use ($matchedProduct) {
                        $sq->where('product_id', $matchedProduct->id);
                    });
                } elseif ($matchedCategory) {
                    $queryBuilder->whereHas('salesOrderItem.product.category', function ($sq) use ($matchedCategory) {
                        $sq->where('slug', $matchedCategory);
                    });
                }

                $items = $queryBuilder->get();
                $totalQty = $items->sum('quantity');
                $totalVal = $items->sum('total_value');
                $shipmentCount = $items->pluck('commercial_shipment_id')->unique()->count();

                $custLabel = $matchedCustomer ? $matchedCustomer->company_name : 'all clients';
                $prodLabel = $matchedProduct ? $matchedProduct->product_name : ($matchedCategory ? ucfirst($matchedCategory).' Products' : 'All Dehydrated Goods');

                if ($items->isEmpty()) {
                    return response()->json([
                        'title' => "Dispatch Record: {$prodLabel} → {$custLabel}",
                        'answer' => "No commercial shipments found for {$prodLabel} to {$custLabel} in the system records.",
                        'details' => [
                            'Customer' => $custLabel,
                            'Product' => $prodLabel,
                            'Total Shipped' => '0 KG (0.00 MT)',
                        ],
                        'action_url' => route('shipments.index'),
                        'action_label' => 'View Shipments Module',
                    ]);
                }

                $details = [
                    'Customer' => $custLabel,
                    'Product' => $prodLabel,
                    'Total Shipped' => number_format($totalQty, 2).' KG ('.number_format($totalQty / 1000, 2).' MT)',
                    'Total Commercial Value' => formatINR($totalVal),
                    'Total Consignments' => "{$shipmentCount} Shipment(s) / LR(s)",
                ];

                // List recent 5 shipments breakdown (sorted by latest date first)
                foreach ($items->take(5) as $it) {
                    $lr = $it->shipment?->lr_number ?? 'LR';
                    $date = $it->shipment?->shipment_date?->format('d M Y') ?? '—';
                    $po = $it->salesOrderItem?->salesOrder?->order_number ?? 'PO';
                    $pName = $it->salesOrderItem?->product?->product_name ?? $prodLabel;
                    $details["LR {$lr} ({$date})"] = "{$pName} · ".number_format($it->quantity)." KG @ ₹{$it->unit_value}/KG = ".formatINR($it->total_value)." (PO: {$po})";
                }

                return response()->json([
                    'title' => "Dispatch Record: {$prodLabel} → {$custLabel}",
                    'answer' => 'A total of '.number_format($totalQty).' KG ('.number_format($totalQty / 1000, 2)." MT) of {$prodLabel} has been shipped to {$custLabel} across {$shipmentCount} consignment(s), with a total commercial value of ".formatINR($totalVal).'.',
                    'details' => $details,
                    'action_url' => $matchedCustomer ? route('customers.show', $matchedCustomer->id) : route('shipments.index'),
                    'action_label' => $matchedCustomer ? "Open {$custLabel} 360 Profile" : 'View All Shipments',
                ]);
            }

            // --- INTENT 6: Outstanding Receivables & AR Ledger Query ---
            if (str_contains($q, 'outstanding') || str_contains($q, 'balance') || str_contains($q, 'receivable') || str_contains($q, 'unpaid') || str_contains($q, 'due') || str_contains($q, 'owe') || str_contains($q, 'ledger') || str_contains($q, 'payment')) {
                if ($matchedCustomer) {
                    $custInvoices = $matchedCustomer->invoices->where('balance_due', '>', 0);
                    $custBalance = $custInvoices->sum('balance_due');
                    $invList = $custInvoices->map(fn ($i) => "{$i->invoice_number} (".formatINR($i->balance_due).', Due: '.($i->due_date ? $i->due_date->format('d M') : '—').')')->join(', ');

                    return response()->json([
                        'title' => "{$matchedCustomer->company_name} Outstanding Ledger",
                        'answer' => "{$matchedCustomer->company_name} has a current outstanding balance of ".formatINR($custBalance).($custInvoices->isNotEmpty() ? ' across '.$custInvoices->count().' open invoice(s).' : ' (All invoices fully settled ✓).'),
                        'details' => [
                            'Company' => $matchedCustomer->company_name,
                            'Total Outstanding' => formatINR($custBalance),
                            'Primary Contact' => $matchedCustomer->primary_contact_person.' ('.$matchedCustomer->primary_phone.')',
                            'Open Invoices' => $invList ?: 'None (Fully Paid)',
                            'Payment Terms' => ($matchedCustomer->payment_terms_days ?: 30).' Days Credit',
                        ],
                        'action_url' => route('customers.show', $matchedCustomer->id),
                        'action_label' => "Open {$matchedCustomer->company_name} Profile",
                    ]);
                }

                $totalOutstanding = Invoice::sum('balance_due');
                $overdueCount = Invoice::where('balance_due', '>', 0)->whereDate('due_date', '<', Carbon::today())->count();
                $topDebtors = Customer::with('invoices')
                    ->get()
                    ->map(fn ($c) => ['name' => $c->company_name, 'balance' => $c->invoices->sum('balance_due')])
                    ->filter(fn ($c) => $c['balance'] > 0)
                    ->sortByDesc('balance');

                $debtorsSummary = [];
                foreach ($topDebtors as $d) {
                    $debtorsSummary[$d['name']] = formatINR($d['balance']);
                }

                return response()->json([
                    'title' => 'Company-Wide Outstanding Accounts Receivable',
                    'answer' => 'Total outstanding receivables across all customers is '.formatINR($totalOutstanding)." ({$overdueCount} overdue invoice(s)).",
                    'details' => $debtorsSummary ?: ['Status' => 'All customer invoices are fully settled!'],
                    'action_url' => route('invoices.index'),
                    'action_label' => 'View Invoices & AR Aging',
                ]);
            }

            // --- INTENT 7: Customer 360 Profile / Contact Overview ---
            if ($matchedCustomer && (str_contains($q, 'about') || str_contains($q, 'contact') || str_contains($q, 'phone') || str_contains($q, 'who') || str_contains($q, 'profile') || str_contains($q, 'status') || str_contains($q, 'detail'))) {
                $totalOrders = $matchedCustomer->salesOrders->sum('total_amount');
                $totalInvoiced = $matchedCustomer->invoices->sum('total_amount');
                $balance = $matchedCustomer->invoices->sum('balance_due');

                return response()->json([
                    'title' => "Customer Profile: {$matchedCustomer->company_name}",
                    'answer' => "{$matchedCustomer->company_name} ({$matchedCustomer->customer_code}) is based in {$matchedCustomer->city}, {$matchedCustomer->state}. Total contracted business is ".formatINR($totalOrders).' with '.formatINR($balance).' current outstanding balance.',
                    'details' => [
                        'Company Name' => $matchedCustomer->company_name,
                        'Customer Code' => $matchedCustomer->customer_code,
                        'Location' => "{$matchedCustomer->city}, {$matchedCustomer->state}",
                        'Primary Contact' => "{$matchedCustomer->primary_contact_person} ({$matchedCustomer->primary_phone})",
                        'Email' => $matchedCustomer->primary_email ?: 'N/A',
                        'GSTIN' => $matchedCustomer->gstin ?: 'Unregistered',
                        'Total Contracted' => formatINR($totalOrders),
                        'Outstanding Balance' => formatINR($balance),
                    ],
                    'action_url' => route('customers.show', $matchedCustomer->id),
                    'action_label' => "Open {$matchedCustomer->company_name} 360 View",
                ]);
            }

            // --- INTENT 8: Product Pricing & Catalog Specification ---
            if ($matchedProduct || (str_contains($q, 'price') || str_contains($q, 'rate') || str_contains($q, 'catalog') || str_contains($q, 'specification') || str_contains($q, 'mesh') || str_contains($q, 'cost'))) {
                if ($matchedProduct) {
                    return response()->json([
                        'title' => "Product Specification: {$matchedProduct->product_name}",
                        'answer' => "{$matchedProduct->product_name} ({$matchedProduct->sku}) has a standard ex-factory rate of ".formatINR($matchedProduct->standard_rate)." per {$matchedProduct->uom}. Export grade quality with standard 80-100 mesh size.",
                        'details' => [
                            'Product Name' => $matchedProduct->product_name,
                            'SKU / Code' => $matchedProduct->sku,
                            'Standard Ex-Factory Rate' => formatINR($matchedProduct->standard_rate)." / {$matchedProduct->uom}",
                            'Category' => $matchedProduct->category?->category_name ?? 'Dehydrated',
                            'Quality Grade' => 'A-Grade Export Quality',
                            'Moisture Standard' => 'Max 5.0%',
                            'Ash Content' => 'Max 4.0%',
                        ],
                        'action_url' => route('orders.index'),
                        'action_label' => 'Create Sales Contract',
                    ]);
                }

                $tenantName = TenantManager::getTenant()?->name ?? config('app.name', 'ERP');
                $prodList = Product::all()->mapWithKeys(fn ($p) => [$p->product_name => formatINR($p->standard_rate)." / {$p->uom}"])->toArray();

                return response()->json([
                    'title' => "{$tenantName} Master Price Catalog",
                    'answer' => 'Product catalog contains '.count($prodList).' active product cuts. Here are the current standard base rates:',
                    'details' => $prodList,
                    'action_url' => route('orders.index'),
                    'action_label' => 'View Sales Orders',
                ]);
            }

            // --- INTENT 9: Samples & Trial Evaluation Tracking ---
            if (str_contains($q, 'sample') || str_contains($q, 'trial') || str_contains($q, 'awb') || str_contains($q, 'courier') || str_contains($q, 'lab')) {
                $samples = Sample::with(['customer', 'product'])->latest()->get();
                if ($matchedCustomer) {
                    $samples = $samples->where('customer_id', $matchedCustomer->id);
                }

                $details = [];
                foreach ($samples->take(5) as $s) {
                    $cName = $s->customer?->company_name ?? 'Client';
                    $pName = $s->product?->product_name ?? 'Product';
                    $details["Sample #{$s->sample_number} ({$cName})"] = "{$pName} ({$s->quantity} {$s->uom}) · Courier: {$s->courier_provider} (AWB: {$s->awb_number}) · Status: {$s->delivery_status}";
                }

                return response()->json([
                    'title' => 'Commercial Evaluation Samples & Lab Trials',
                    'answer' => 'Tracking '.$samples->count().' active evaluation sample dispatches across courier networks (DTDC, Professional, FedEx).',
                    'details' => $details ?: ['Status' => 'No active evaluation samples logged.'],
                    'action_url' => route('customers.index'),
                    'action_label' => 'View Customer Samples',
                ]);
            }

            // --- INTENT 10: Production Batches & Inventory Stocks ---
            if (str_contains($q, 'stock') || str_contains($q, 'inventory') || str_contains($q, 'batch') || str_contains($q, 'godown') || str_contains($q, 'production') || str_contains($q, 'factory')) {
                $garlicStock = InventoryStock::whereHas('product.category', fn ($c) => $c->where('slug', 'garlic'))->sum('current_stock_qty');
                $onionStock = InventoryStock::whereHas('product.category', fn ($c) => $c->where('slug', 'onion'))->sum('current_stock_qty');
                $batches = ProductionBatch::with('product')->latest('start_date')->take(5)->get();

                $batchDetails = [];
                foreach ($batches as $b) {
                    $batchDetails["Batch #{$b->batch_number}"] = ($b->product?->product_name ?? 'Product').' · Produced: '.number_format($b->output_qty)." KG · Status: {$b->status}";
                }

                return response()->json([
                    'title' => 'Bhavnagar Plant Production Batches & Inventory',
                    'answer' => 'Factory stocks show '.number_format($garlicStock + $onionStock).' KG total dehydrated goods across Bhavnagar Godowns A & B with '.$batches->count().' production batches on record.',
                    'details' => array_merge([
                        'Garlic Finished Stock' => number_format($garlicStock).' KG',
                        'Onion Finished Stock' => number_format($onionStock).' KG',
                    ], $batchDetails),
                    'action_url' => route('production.index'),
                    'action_label' => 'View Production Batches',
                ]);
            }

            // --- INTENT 11: Follow-up Tasks & Daily Agenda ---
            if (str_contains($q, 'task') || str_contains($q, 'today') || str_contains($q, 'priority') || str_contains($q, 'action') || str_contains($q, 'do next') || str_contains($q, 'agenda')) {
                $urgentTasks = FollowupTask::with('customer')->where('status', 'PENDING')->orderBy('due_date')->take(5)->get();
                $taskDetails = [];
                foreach ($urgentTasks as $t) {
                    $cName = $t->customer?->company_name ?? 'Client';
                    $taskDetails['Due: '.$t->due_date->format('d M')." ({$cName})"] = "{$t->reason} → Action: {$t->next_action}";
                }

                return response()->json([
                    'title' => "Today's Operational Command & Priority Follow-ups",
                    'answer' => 'You have '.$urgentTasks->count().' pending priority follow-ups requiring action today.',
                    'details' => $taskDetails ?: ['Status' => 'All follow-up actions for today are completed! ✓'],
                    'action_url' => route('dashboard'),
                    'action_label' => 'Open Daily Command Center',
                ]);
            }

            // --- INTENT 12: General Platform / Business Summary ---
            $totalRev = SalesOrder::sum('total_amount');
            $totalOut = Invoice::sum('balance_due');
            $totalShipped = CommercialShipmentItem::sum('quantity');
            $custCount = Customer::count();

            $tenantName = TenantManager::getTenant()?->name ?? config('app.name', 'ERP');

            return response()->json([
                'title' => "{$tenantName} System Summary",
                'answer' => "I searched the ERP database across {$custCount} client accounts, ".Product::count().' SKUs, and live logistics feeds. Total contracted order book is '.formatINR($totalRev).', total material dispatched is '.number_format($totalShipped / 1000, 2).' MT, and current outstanding AR is '.formatINR($totalOut).'.',
                'details' => [
                    'Total Customers' => "{$custCount} Key Accounts",
                    'Total Sales Contracts' => formatINR($totalRev),
                    'Dispatched Material' => number_format($totalShipped).' KG ('.number_format($totalShipped / 1000, 2).' MT)',
                    'Outstanding Receivables' => formatINR($totalOut),
                ],
                'action_url' => route('dashboard'),
                'action_label' => 'Open Executive Cockpit',
            ]);
        } catch (\Throwable $e) {
            Log::error('AI Copilot query failed: '.$e->getMessage(), [
                'exception' => $e,
                'query' => $rawQuery,
            ]);

            return response()->json([
                'title' => 'Search Query Notice',
                'answer' => 'An error occurred while analyzing records for your query. Please try searching with a customer name, PO number, or commodity grade.',
                'details' => config('app.debug') ? ['Debug Note' => $e->getMessage()] : [],
                'action_url' => route('dashboard'),
                'action_label' => 'Go to Dashboard',
            ]);
        }
    }
}
