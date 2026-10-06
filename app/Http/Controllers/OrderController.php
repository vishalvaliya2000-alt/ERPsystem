<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Customer;
use App\Models\FollowupTask;
use App\Models\Product;
use App\Models\SalesOrder;
use App\Models\SalesOrderItem;
use App\Services\CompanyFirmContext;
use App\Services\DocumentTransactionService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class OrderController extends Controller
{
    protected DocumentTransactionService $documentService;

    public function __construct(DocumentTransactionService $documentService)
    {
        $this->documentService = $documentService;
    }

    public function index()
    {
        $orders = SalesOrder::with(['customer', 'items.product', 'shipments.items', 'invoices', 'revisions', 'poDocument.activeVersion'])
            ->orderByDesc('order_date')
            ->get();

        $customers = Customer::orderBy('company_name')->get();
        $products = Product::where('is_active', true)->orderBy('product_name')->get();

        // High-level fulfillment & order book metrics
        $totalOrdersCount = $orders->count();
        $pendingOrdersCount = $orders->filter(fn ($o) => (float) $o->items->sum('shipped_qty') == 0 && (float) $o->items->sum('order_qty') > 0)->count();
        $partialOrdersCount = $orders->filter(fn ($o) => (float) $o->items->sum('shipped_qty') > 0 && (float) $o->items->sum('balance_qty') > 0)->count();
        $completedOrdersCount = $orders->filter(fn ($o) => (float) $o->items->sum('balance_qty') <= 0 || $o->status === 'COMPLETED')->count();

        $totalCommittedValue = (float) $orders->sum('total_amount');
        $totalCommittedQty = (float) $orders->reduce(fn ($carry, $o) => $carry + $o->items->sum('order_qty'), 0);
        $totalShippedQty = (float) $orders->reduce(fn ($carry, $o) => $carry + $o->items->sum('shipped_qty'), 0);
        $totalPendingQty = (float) $orders->reduce(fn ($carry, $o) => $carry + $o->items->sum('balance_qty'), 0);
        $overallFulfillmentPct = $totalCommittedQty > 0 ? round(($totalShippedQty / $totalCommittedQty) * 100, 1) : 0;

        return view('orders.index', compact(
            'orders',
            'customers',
            'products',
            'totalOrdersCount',
            'pendingOrdersCount',
            'partialOrdersCount',
            'completedOrdersCount',
            'totalCommittedValue',
            'totalCommittedQty',
            'totalShippedQty',
            'totalPendingQty',
            'overallFulfillmentPct'
        ));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'customer_id' => 'required|exists:customers,id',
            'po_number' => 'required|string|max:100',
            'order_date' => 'required|date',
            'payment_terms' => 'nullable|string|max:100',
            'advance_required' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string',
            'po_document' => 'nullable|file|mimes:pdf,jpg,jpeg,png,webp|max:25600',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.order_qty' => 'required|numeric|min:0.01',
            'items.*.rate' => 'required|numeric|min:0.01',
        ]);

        DB::beginTransaction();
        try {
            $totalOrderVal = 0;
            foreach ($validated['items'] as $it) {
                $totalOrderVal += ((float) $it['order_qty'] * (float) $it['rate']);
            }

            // Enforce Customer Credit Limit Check
            $cust = Customer::findOrFail($validated['customer_id']);
            $creditCheck = $cust->checkCreditLimit($totalOrderVal);
            if (! $creditCheck['allowed']) {
                DB::rollBack();

                return back()
                    ->withInput()
                    ->with('error', $creditCheck['message']);
            }
            if (! empty($creditCheck['message']) && $creditCheck['action'] === 'WARN') {
                session()->flash('warning', $creditCheck['message']);
            }

            $orderNo = $validated['po_number'];
            if (empty($request->confirm_duplicate)) {
                if (SalesOrder::where('po_number', $validated['po_number'])->orWhere('order_number', $validated['po_number'])->exists()) {
                    DB::rollBack();

                    return back()
                        ->withInput()
                        ->with('error', "A Sales Order with this PO Number ({$validated['po_number']}) already exists. To proceed anyway, please check 'Confirm Duplicate' and submit again.")
                        ->with('requires_duplicate_confirmation', true);
                }
            }

            // If user confirmed, ensure unique order_number
            if (SalesOrder::where('order_number', $orderNo)->exists()) {
                $orderNo = $validated['po_number'].'-'.rand(10, 99);
            }

            $advanceRequired = (float) ($validated['advance_required'] ?? 0);
            $initialStatus = $advanceRequired > 0 ? 'ADVANCE_PENDING' : 'CONFIRMED';

            $order = SalesOrder::create([
                'firm_id' => CompanyFirmContext::getCurrentFirmId(),
                'order_number' => $orderNo,
                'po_number' => $validated['po_number'],
                'po_date' => Carbon::parse($validated['order_date']),
                'customer_id' => $validated['customer_id'],
                'order_date' => Carbon::parse($validated['order_date']),
                'subtotal' => $totalOrderVal,
                'tax_amount' => 0,
                'total_amount' => $totalOrderVal,
                'advance_required' => $advanceRequired,
                'advance_received' => 0,
                'balance_amount' => $totalOrderVal,
                'payment_terms' => $validated['payment_terms'] ?? '30 Days Credit',
                'status' => $initialStatus,
                'notes' => $validated['notes'] ?? null,
            ]);

            foreach ($validated['items'] as $itemData) {
                $qty = (float) $itemData['order_qty'];
                $rate = (float) $itemData['rate'];
                $itemVal = $qty * $rate;

                SalesOrderItem::create([
                    'sales_order_id' => $order->id,
                    'product_id' => $itemData['product_id'],
                    'order_qty' => $qty,
                    'rate' => $rate,
                    'order_value' => $itemVal,
                    'shipped_qty' => 0,
                    'balance_qty' => $qty,
                    'status' => 'PENDING',
                ]);
            }

            // Attach Customer PO Document if provided directly in the form
            if ($request->hasFile('po_document')) {
                $this->documentService->attachCustomerPo(
                    $order,
                    $request->file('po_document'),
                    $validated['notes'] ?? null
                );
            }

            $cust = Customer::find($validated['customer_id']);
            $cust->update(['last_order_date' => $order->order_date]);

            // Auto-schedule production follow-up
            FollowupTask::create([
                'customer_id' => $cust->id,
                'type' => 'SALES_FOLLOWUP',
                'reason' => "New Purchase Order {$order->order_number} received for ".formatINR($totalOrderVal),
                'priority' => 'HIGH',
                'due_date' => Carbon::now()->addDays(2),
                'status' => 'PENDING',
                'pending_item' => 'Batch packaging & quality clearance',
                'next_action' => 'Check production inventory readiness and schedule first partial dispatch',
            ]);

            ActivityLog::create([
                'customer_id' => $cust->id,
                'activity_type' => 'NOTE',
                'title' => "Purchase Order Received ({$order->order_number})",
                'description' => 'Total order value: '.formatINR($totalOrderVal).' with '.count($validated['items']).' product line item(s).',
            ]);

            DB::commit();

            return redirect()->route('orders.index')->with('success', "✓ Sales Order {$order->order_number} created successfully with ".count($validated['items']).' item(s)!');
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('Failed to create sales order: '.$e->getMessage(), [
                'exception' => $e,
                'request' => $request->all(),
            ]);

            return back()->withInput()->with('error', 'Failed to create sales order: '.$e->getMessage());
        }
    }

    public function update(Request $request, $id)
    {
        $order = SalesOrder::with(['items.product', 'shipments', 'invoices'])->findOrFail($id);

        // RULE: Cannot edit if fully delivered
        if ($order->isFullyDelivered() || $order->status === 'COMPLETED') {
            return back()->with('error', "🚫 Disallowed: Purchase Order {$order->order_number} has already been FULLY DELIVERED. It cannot be edited.");
        }

        $validated = $request->validate([
            'po_number' => 'required|string|max:100',
            'order_date' => 'required|date',
            'payment_terms' => 'nullable|string|max:100',
            'advance_required' => 'nullable|numeric|min:0',
            'revision_reason' => 'nullable|string|max:500',
            'notes' => 'nullable|string',
            'items' => 'required|array|min:1',
            'items.*.id' => 'nullable|integer',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.order_qty' => 'required|numeric|min:0.01',
            'items.*.rate' => 'required|numeric|min:0.01',
        ]);

        DB::beginTransaction();
        try {
            // Automatically capture immutable revision snapshot before applying modifications
            $revisionReason = trim($request->input('revision_reason') ?? '');
            if (empty($revisionReason)) {
                $revisionReason = 'Order amendment / terms revision';
            }

            $user = auth()->user();
            $userName = $user ? $user->name : 'Administrator';
            $userId = $user ? $user->id : null;

            $order->createRevisionSnapshot($revisionReason, $userName, $userId);

            $totalOrderVal = 0;
            foreach ($validated['items'] as $it) {
                $totalOrderVal += ((float) $it['order_qty'] * (float) $it['rate']);
            }

            $newAdvReq = isset($validated['advance_required']) ? (float) $validated['advance_required'] : (float) $order->advance_required;
            $newBal = max(0, $totalOrderVal - (float) $order->advance_received);
            $newStatus = $order->status;
            if ($newStatus === 'ADVANCE_PENDING' && ($newAdvReq <= 0 || (float) $order->advance_received >= $newAdvReq)) {
                $newStatus = 'CONFIRMED';
            } elseif ($newStatus === 'CONFIRMED' && $newAdvReq > 0 && (float) $order->advance_received < $newAdvReq) {
                $newStatus = 'ADVANCE_PENDING';
            }

            $order->update([
                'po_number' => $validated['po_number'],
                'po_date' => Carbon::parse($validated['order_date']),
                'order_date' => Carbon::parse($validated['order_date']),
                'subtotal' => $totalOrderVal,
                'total_amount' => $totalOrderVal,
                'advance_required' => $newAdvReq,
                'balance_amount' => $newBal,
                'status' => $newStatus,
                'payment_terms' => $validated['payment_terms'] ?? '30 Days Credit',
                'notes' => $validated['notes'] ?? null,
            ]);

            $existingItemMap = $order->items->keyBy('id');
            $submittedItemIds = [];

            foreach ($validated['items'] as $itemData) {
                $itemId = $itemData['id'] ?? null;
                $pId = $itemData['product_id'];
                $qty = (float) $itemData['order_qty'];
                $rate = (float) $itemData['rate'];

                if ($itemId && isset($existingItemMap[$itemId])) {
                    // Update existing line item (preserving historic shipments)
                    $existing = $existingItemMap[$itemId];
                    $shipped = (float) $existing->shipped_qty;

                    if ($qty < $shipped) {
                        DB::rollBack();

                        return back()->with('error', "🚫 Order quantity ({$qty} KG) cannot be lower than the already dispatched quantity ({$shipped} KG) for {$existing->product?->product_name}.");
                    }

                    $balance = max(0, $qty - $shipped);
                    $status = $balance <= 0 ? 'COMPLETED' : ($shipped > 0 ? 'PARTIAL' : 'PENDING');

                    $existing->update([
                        'product_id' => $pId,
                        'order_qty' => $qty,
                        'rate' => $rate,
                        'order_value' => $qty * $rate,
                        'balance_qty' => $balance,
                        'status' => $status,
                    ]);

                    $submittedItemIds[] = $existing->id;
                } else {
                    // New line item (e.g. price revision tier or additional cut)
                    $newItem = SalesOrderItem::create([
                        'sales_order_id' => $order->id,
                        'product_id' => $pId,
                        'order_qty' => $qty,
                        'rate' => $rate,
                        'order_value' => $qty * $rate,
                        'shipped_qty' => 0,
                        'balance_qty' => $qty,
                        'status' => 'PENDING',
                    ]);

                    $submittedItemIds[] = $newItem->id;
                }
            }

            // Remove any line items deleted by user (only if 0 shipped quantity)
            foreach ($order->items as $it) {
                if (! in_array($it->id, $submittedItemIds)) {
                    if ($it->shipped_qty > 0) {
                        DB::rollBack();

                        return back()->with('error', "🚫 Cannot delete line item for {$it->product?->product_name} because {$it->shipped_qty} KG has already been dispatched.");
                    }
                    $it->delete();
                }
            }

            // Re-evaluate overall order status
            $totalShipped = $order->items()->sum('shipped_qty');
            $totalBalance = $order->items()->sum('balance_qty');
            $newOrderStatus = $totalShipped <= 0 ? 'CONFIRMED' : ($totalBalance <= 0 ? 'COMPLETED' : 'PARTIALLY_DISPATCHED');
            $order->update(['status' => $newOrderStatus]);

            ActivityLog::create([
                'customer_id' => $order->customer_id,
                'activity_type' => 'NOTE',
                'title' => "Purchase Order Revised to Rev {$order->revision_number} ({$order->order_number})",
                'description' => "Reason: {$revisionReason} | Updated order value: ".formatINR($totalOrderVal).' with '.count($validated['items']).' line item(s).',
            ]);

            DB::commit();

            return redirect()->route('orders.index')->with('success', "✓ Purchase Order {$order->order_number} revised to Rev {$order->revision_number} successfully! (Previous version safely archived to history)");
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error("Failed to update sales order #{$id}: ".$e->getMessage(), [
                'exception' => $e,
                'request' => $request->all(),
            ]);

            return back()->withInput()->with('error', 'Failed to update order: '.$e->getMessage());
        }
    }

    public function revisions($id)
    {
        $order = SalesOrder::with(['customer', 'items.product', 'revisions'])->findOrFail($id);

        return response()->json([
            'order_id' => $order->id,
            'order_number' => $order->order_number,
            'po_number' => $order->po_number,
            'current_revision' => (int) ($order->revision_number ?? 0),
            'current' => [
                'revision_number' => (int) ($order->revision_number ?? 0),
                'last_revised_at' => $order->last_revised_at ? $order->last_revised_at->format('d M Y, h:i A') : null,
                'last_revision_reason' => $order->last_revision_reason,
                'total_amount' => (float) $order->total_amount,
                'total_qty' => (float) $order->items->sum('order_qty'),
                'status' => $order->status,
                'payment_terms' => $order->payment_terms,
                'notes' => $order->notes,
                'items' => $order->items->map(fn ($it) => [
                    'id' => $it->id,
                    'product_id' => $it->product_id,
                    'product_name' => $it->product?->product_name ?? 'N/A',
                    'product_code' => $it->product?->product_code ?? '',
                    'order_qty' => (float) $it->order_qty,
                    'rate' => (float) $it->rate,
                    'order_value' => (float) $it->order_value,
                    'shipped_qty' => (float) $it->shipped_qty,
                    'balance_qty' => (float) $it->balance_qty,
                ]),
            ],
            'history' => $order->revisions->map(fn ($rev) => [
                'id' => $rev->id,
                'revision_number' => $rev->revision_number,
                'revision_reason' => $rev->revision_reason,
                'revised_by_name' => $rev->revised_by_name,
                'created_at' => $rev->created_at ? $rev->created_at->format('d M Y, h:i A') : null,
                'snapshot' => $rev->snapshot,
            ]),
        ]);
    }

    public function destroy($id)
    {
        $order = SalesOrder::with(['items', 'shipments', 'invoices'])->findOrFail($id);

        // RULE 1: Cannot delete if fully delivered
        if ($order->isFullyDelivered() || $order->status === 'COMPLETED') {
            return back()->with('error', "🚫 Disallowed: Purchase Order {$order->order_number} has already been FULLY DELIVERED. It cannot be deleted.");
        }

        // RULE 2: Cannot delete if active shipments exist
        if ($order->shipments()->count() > 0) {
            return back()->with('error', "🚫 Disallowed: Cannot delete PO {$order->order_number} because {$order->shipments()->count()} shipment(s) have already been dispatched against it.");
        }

        // RULE 3: Cannot delete if invoices exist
        if ($order->invoices()->count() > 0) {
            return back()->with('error', "🚫 Disallowed: Cannot delete PO {$order->order_number} because tax invoice(s) have already been issued against it.");
        }

        // RULE 4: Cannot delete if advance payment has been recorded
        if ((float) $order->advance_received > 0 || $order->advanceReceipts()->count() > 0) {
            return back()->with('error', "🚫 Disallowed: Cannot delete PO {$order->order_number} because an advance payment of ".formatINR($order->advance_received).' has already been recorded against it.');
        }

        DB::beginTransaction();
        try {
            $orderNo = $order->order_number;
            $custId = $order->customer_id;

            // Delete line items and order
            $order->items()->delete();
            $order->delete();

            ActivityLog::create([
                'customer_id' => $custId,
                'activity_type' => 'NOTE',
                'title' => "Purchase Order Deleted ({$orderNo})",
                'description' => 'Cancelled / deleted Purchase Order record.',
            ]);

            DB::commit();

            return redirect()->route('orders.index')->with('success', "✓ Purchase Order {$orderNo} has been deleted.");
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error("Failed to delete sales order #{$id}: ".$e->getMessage(), [
                'exception' => $e,
            ]);

            return back()->with('error', 'Failed to delete order: '.$e->getMessage());
        }
    }

    /**
     * Record an advance payment received against this Sales Order (PO).
     */
    public function recordAdvance(Request $request, $id)
    {
        $order = SalesOrder::with(['customer', 'invoices'])->findOrFail($id);

        if (! $request->has('amount_received') && $request->has('amount')) {
            $request->merge(['amount_received' => $request->input('amount')]);
        }

        $validated = $request->validate([
            'amount_received' => 'required|numeric|min:0.01',
            'payment_mode' => 'required|string',
            'reference_number' => 'nullable|string|max:100',
            'receipt_date' => 'required|date',
            'remarks' => 'nullable|string|max:500',
        ]);

        $amt = (float) $validated['amount_received'];
        $remainingContractBalance = max(0, (float) $order->total_amount - (float) $order->advance_received);

        if ($amt > ($remainingContractBalance + 0.01)) {
            return back()->with('error', '🚫 Disallowed: Advance amount ('.formatINR($amt).') exceeds remaining contract balance ('.formatINR($remainingContractBalance).") on PO {$order->order_number}.");
        }

        DB::beginTransaction();
        try {
            $receipt = $order->recordAdvance(
                $amt,
                $validated['payment_mode'],
                $validated['reference_number'] ?? null,
                $validated['remarks'] ?? null,
                $validated['receipt_date']
            );

            // Deduct customer outstanding balance
            $cust = $order->customer;
            if ($cust && (float) $cust->outstanding_amount > 0) {
                $cust->decrement('outstanding_amount', min($amt, (float) $cust->outstanding_amount));
            }

            DB::commit();

            return back()->with('success', '✓ Advance payment of '.formatINR($amt)." recorded against PO {$order->order_number} via {$receipt->payment_mode} (Ref: {$receipt->receipt_number})!");
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error("Failed to record advance on sales order #{$id}: ".$e->getMessage(), [
                'exception' => $e,
                'request' => $request->all(),
            ]);

            return back()->withInput()->with('error', 'Failed to record advance: '.$e->getMessage());
        }
    }
}
