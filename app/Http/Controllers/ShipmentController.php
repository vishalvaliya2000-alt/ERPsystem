<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\CommercialShipment;
use App\Models\CommercialShipmentItem;
use App\Models\FollowupTask;
use App\Models\SalesOrder;
use App\Models\SalesOrderItem;
use App\Models\Transporter;
use App\Services\CompanyFirmContext;
use App\Services\DocumentTransactionService;
use App\Services\TransporterTrackingService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ShipmentController extends Controller
{
    protected DocumentTransactionService $documentService;

    public function __construct(DocumentTransactionService $documentService)
    {
        $this->documentService = $documentService;
    }

    public function index()
    {
        $shipments = CommercialShipment::with([
            'salesOrder.customer',
            'items.salesOrderItem.product',
            'items.salesOrderItem.salesOrder.customer',
            'lrDocument.activeVersion',
            'ewayBill',
        ])
            ->orderByDesc('shipment_date')
            ->get();

        // Fetch all open orders with their pending line items (> 0 KG)
        $openOrders = SalesOrder::with(['customer', 'items' => function ($q) {
            $q->where('balance_qty', '>', 0)->with('product');
        }])
            ->whereHas('items', function ($q) {
                $q->where('balance_qty', '>', 0);
            })
            ->whereNotIn('status', ['COMPLETED', 'CANCELLED'])
            ->get();

        // Flat list of all unshipped order items for multi-PO selection
        $availableItems = [];
        foreach ($openOrders as $order) {
            foreach ($order->items as $it) {
                $availableItems[] = [
                    'id' => $it->id,
                    'order_id' => $order->id,
                    'order_number' => $order->order_number,
                    'customer_name' => $order->customer->company_name ?? 'Client',
                    'product_name' => $it->product->product_name ?? 'Product',
                    'balance_qty' => (float) $it->balance_qty,
                    'rate' => (float) $it->rate,
                    'label' => "{$order->order_number} ({$order->customer->company_name}) · {$it->product->product_name} [Bal: ".number_format($it->balance_qty)." KG @ ₹{$it->rate}]",
                ];
            }
        }

        $transporters = Transporter::where('is_active', true)->orderBy('transporter_name')->get();

        return view('shipments.index', compact('shipments', 'openOrders', 'availableItems', 'transporters'));
    }

    public function store(Request $request)
    {
        $targetStatus = $request->input('status', 'DISPATCHED');
        $isScheduled = ($targetStatus === 'SCHEDULED');

        $validated = $request->validate([
            'transporter' => $isScheduled ? 'nullable|string|max:100' : 'required|string|max:100',
            'lr_number' => $isScheduled
                ? 'nullable|string|max:100|unique:commercial_shipments,lr_number'
                : 'required|string|max:100|unique:commercial_shipments,lr_number',
            'vehicle_number' => 'nullable|string|max:50',
            'destination' => 'nullable|string|max:255',
            'shipment_date' => 'required|date',
            'freight_payment_type' => 'required|in:TO_PAY,PAID',
            'freight_amount' => 'nullable|numeric|min:0',
            'status' => 'nullable|string',
            'notes' => 'nullable|string',
            'lr_document' => 'nullable|file|mimes:pdf,jpg,jpeg,png,webp|max:25600',
            'items' => 'required|array|min:1',
            'items.*.sales_order_item_id' => 'required|exists:sales_order_items,id',
            'items.*.quantity' => 'required|numeric|min:0.01',
        ], [
            'lr_number.unique' => "🚫 Disallowed: LR / Bilty Number ':input' is already registered for an existing commercial shipment. Each LR must be unique.",
        ]);

        DB::beginTransaction();
        try {
            $proofPath = null;
            $shipmentNumber = 'SHP-'.date('Ymd').'-'.rand(100, 999);
            $affectedOrderIds = [];
            $totalConsignmentQty = 0;
            $firstOrderId = null;
            $freightAmount = ($validated['freight_payment_type'] === 'PAID') ? (float) ($validated['freight_amount'] ?? 0) : 0;
            $status = $isScheduled ? 'SCHEDULED' : ($validated['status'] ?? 'DISPATCHED');
            $transporterName = ! empty($validated['transporter']) ? $validated['transporter'] : ($isScheduled ? 'TBD (To Be Decided)' : 'Direct Factory Handover');
            $lrNum = ! empty($validated['lr_number']) ? $validated['lr_number'] : null;

            // Pre-validation of all line items
            foreach ($validated['items'] as $itData) {
                $orderItem = SalesOrderItem::with('product', 'salesOrder')->findOrFail($itData['sales_order_item_id']);
                $qty = (float) $itData['quantity'];

                if ($qty > (float) $orderItem->balance_qty) {
                    DB::rollBack();

                    return back()->with('error', '🚫 Disallowed: Dispatch quantity ('.number_format($qty, 2).' KG) exceeds remaining balance ('.number_format($orderItem->balance_qty, 2)." KG) for {$orderItem->product->product_name} on PO {$orderItem->salesOrder->order_number}.");
                }
            }

            // Create Master Commercial Shipment
            $shipment = CommercialShipment::create([
                'firm_id' => CompanyFirmContext::getCurrentFirmId(),
                'shipment_number' => $shipmentNumber,
                'sales_order_id' => null,
                'shipment_date' => Carbon::parse($validated['shipment_date']),
                'transporter' => $transporterName,
                'lr_number' => $lrNum,
                'vehicle_number' => $validated['vehicle_number'] ?? null,
                'destination' => $validated['destination'] ?? 'Customer Godown',
                'delivery_type' => 'Door Delivery',
                'freight_payment_type' => $validated['freight_payment_type'],
                'freight_amount' => $freightAmount,
                'status' => $status,
                'notes' => $validated['notes'] ?? null,
                'proof_document_url' => $proofPath,
            ]);

            // Create line items and deduct from respective POs
            foreach ($validated['items'] as $itData) {
                $orderItem = SalesOrderItem::with('product', 'salesOrder')->findOrFail($itData['sales_order_item_id']);
                $order = $orderItem->salesOrder;
                $qty = (float) $itData['quantity'];
                $itemVal = $qty * (float) $orderItem->rate;

                if (! $firstOrderId) {
                    $firstOrderId = $order->id;
                }
                $affectedOrderIds[$order->id] = $order;
                $totalConsignmentQty += $qty;

                CommercialShipmentItem::create([
                    'commercial_shipment_id' => $shipment->id,
                    'sales_order_item_id' => $orderItem->id,
                    'quantity' => $qty,
                    'unit_value' => $orderItem->rate,
                    'total_value' => $itemVal,
                ]);

                // Deduct balance
                $newShipped = $orderItem->shipped_qty + $qty;
                $newBalance = max(0, $orderItem->balance_qty - $qty);
                $itemStatus = $newBalance <= 0 ? 'COMPLETED' : 'PARTIAL';

                $orderItem->update([
                    'shipped_qty' => $newShipped,
                    'balance_qty' => $newBalance,
                    'status' => $itemStatus,
                ]);
            }

            $shipment->update(['sales_order_id' => $firstOrderId]);

            // Update status of all involved Sales Orders
            $completedOrdersCount = 0;
            foreach ($affectedOrderIds as $ordId => $ord) {
                $remainingBal = $ord->items()->sum('balance_qty');
                $ordStatus = $remainingBal <= 0 ? 'COMPLETED' : 'PARTIALLY_DISPATCHED';
                $ord->update([
                    'status' => $ordStatus,
                    'actual_dispatch_date' => ($remainingBal <= 0 && $status !== 'SCHEDULED') ? Carbon::parse($validated['shipment_date']) : $ord->actual_dispatch_date,
                ]);

                if ($ordStatus === 'COMPLETED') {
                    $completedOrdersCount++;
                }

                if ($status === 'SCHEDULED') {
                    // Task to alert on scheduled dispatch day
                    FollowupTask::create([
                        'customer_id' => $ord->customer_id,
                        'type' => 'DISPATCH_SCHEDULE',
                        'reason' => "Scheduled shipment {$shipment->shipment_number} due on ".Carbon::parse($validated['shipment_date'])->format('d M Y')." for PO {$ord->order_number}",
                        'priority' => 'HIGH',
                        'due_date' => Carbon::parse($validated['shipment_date']),
                        'status' => 'PENDING',
                        'pending_item' => 'Vehicle loading & LR generation for '.number_format($totalConsignmentQty).' KG',
                        'next_action' => "Confirm truck placement with {$transporterName} and execute loading at factory",
                    ]);

                    ActivityLog::create([
                        'customer_id' => $ord->customer_id,
                        'activity_type' => 'SHIPMENT',
                        'title' => "Shipment Scheduled ({$shipment->shipment_number})",
                        'description' => 'Scheduled dispatch of '.number_format($totalConsignmentQty).' KG on '.Carbon::parse($validated['shipment_date'])->format('d M Y')." against PO {$ord->order_number}.",
                    ]);
                } else {
                    // Follow-up task for delivery confirmation
                    FollowupTask::create([
                        'customer_id' => $ord->customer_id,
                        'type' => 'DELIVERY_CONFIRMATION',
                        'reason' => 'Material dispatched under LR '.($shipment->lr_number ?: $shipment->shipment_number)." ({$shipment->transporter}) for PO {$ord->order_number}",
                        'priority' => 'HIGH',
                        'due_date' => Carbon::now()->addDays(3),
                        'status' => 'PENDING',
                        'pending_item' => "Delivery confirmation at {$shipment->destination}",
                        'next_action' => "Track consignment with {$shipment->transporter} and confirm arrival with client",
                    ]);

                    ActivityLog::create([
                        'customer_id' => $ord->customer_id,
                        'activity_type' => 'SHIPMENT',
                        'title' => 'Consignment Dispatched (LR: '.($shipment->lr_number ?: $shipment->shipment_number).')',
                        'description' => 'Dispatched material under LR '.($shipment->lr_number ?: 'N/A')." via {$shipment->transporter} (".($shipment->freight_payment_type === 'PAID' ? 'Paid Freight: '.formatINR($freightAmount) : 'TO PAY').") against PO {$ord->order_number}.",
                    ]);
                }
            }

            // Attach Transport LR Document to Document Centre and link to Shipment
            if ($request->hasFile('lr_document')) {
                $this->documentService->attachShipmentLr(
                    $shipment,
                    $request->file('lr_document'),
                    $validated['notes'] ?? null
                );
            }

            DB::commit();

            $successMsg = ($status === 'SCHEDULED')
                ? "📅 Shipment plan {$shipment->shipment_number} scheduled for ".Carbon::parse($validated['shipment_date'])->format('d M Y').' with '.number_format($totalConsignmentQty).' KG across '.count($affectedOrderIds).' PO(s)!'
                : "✓ Consignment {$shipment->lr_number} created successfully (".($shipment->freight_payment_type === 'PAID' ? 'Freight Paid: '.formatINR($freightAmount) : 'Freight: TO PAY').') with '.count($validated['items']).' item(s) across '.count($affectedOrderIds).' PO(s)!';

            return redirect()->route('shipments.index')->with('success', $successMsg);
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('Shipment creation failed: '.$e->getMessage(), [
                'exception' => $e,
                'request' => $request->all(),
            ]);

            return back()->withInput()->with('error', 'Shipment creation failed: '.$e->getMessage());
        }
    }

    public function update(Request $request, $id)
    {
        $shipment = CommercialShipment::with(['salesOrder.customer'])->findOrFail($id);
        $wasScheduled = ($shipment->status === 'SCHEDULED');
        $targetStatus = $request->input('status', $shipment->status);
        $isNowDispatched = in_array($targetStatus, ['DISPATCHED', 'IN_TRANSIT']);

        $validated = $request->validate([
            'transporter' => ($isNowDispatched && empty($shipment->transporter)) ? 'required|string|max:100' : 'nullable|string|max:100',
            'lr_number' => $isNowDispatched
                ? 'required|string|max:100|unique:commercial_shipments,lr_number,'.$id
                : 'nullable|string|max:100|unique:commercial_shipments,lr_number,'.$id,
            'vehicle_number' => 'nullable|string|max:50',
            'destination' => 'nullable|string|max:255',
            'shipment_date' => 'required|date',
            'freight_payment_type' => 'required|in:TO_PAY,PAID',
            'freight_amount' => 'nullable|numeric|min:0',
            'status' => 'nullable|string',
            'notes' => 'nullable|string',
            'lr_document' => 'nullable|file|mimes:pdf,jpg,jpeg,png,webp|max:25600',
        ], [
            'lr_number.unique' => "🚫 Disallowed: LR / Bilty Number ':input' is already registered for another commercial shipment.",
        ]);

        DB::beginTransaction();
        try {
            $freightAmount = ($validated['freight_payment_type'] === 'PAID') ? (float) ($validated['freight_amount'] ?? 0) : 0;
            $newStatus = $validated['status'] ?? $shipment->status;

            $data = [
                'transporter' => $validated['transporter'] ?: ($shipment->transporter ?: 'TBD (To Be Decided)'),
                'lr_number' => ! empty($validated['lr_number']) ? $validated['lr_number'] : $shipment->lr_number,
                'vehicle_number' => $validated['vehicle_number'] ?? null,
                'destination' => $validated['destination'] ?? null,
                'shipment_date' => Carbon::parse($validated['shipment_date']),
                'freight_payment_type' => $validated['freight_payment_type'],
                'freight_amount' => $freightAmount,
                'status' => $newStatus,
                'notes' => $validated['notes'] ?? null,
            ];

            $shipment->update($data);

            if ($request->hasFile('lr_document')) {
                $this->documentService->attachShipmentLr(
                    $shipment,
                    $request->file('lr_document'),
                    $validated['notes'] ?? null
                );
            }

            // If a scheduled shipment just got dispatched, create delivery task and log
            if ($wasScheduled && $isNowDispatched) {
                if ($shipment->salesOrder) {
                    FollowupTask::create([
                        'customer_id' => $shipment->salesOrder->customer_id,
                        'type' => 'DELIVERY_CONFIRMATION',
                        'reason' => "Material dispatched under LR {$shipment->lr_number} ({$shipment->transporter}) for PO {$shipment->salesOrder->order_number}",
                        'priority' => 'HIGH',
                        'due_date' => Carbon::now()->addDays(3),
                        'status' => 'PENDING',
                        'pending_item' => "Delivery confirmation at {$shipment->destination}",
                        'next_action' => "Track consignment with {$shipment->transporter} and confirm arrival with client",
                    ]);

                    ActivityLog::create([
                        'customer_id' => $shipment->salesOrder->customer_id,
                        'activity_type' => 'SHIPMENT',
                        'title' => "Consignment Dispatched (LR: {$shipment->lr_number})",
                        'description' => "Dispatched planned consignment {$shipment->shipment_number} under LR {$shipment->lr_number} via {$shipment->transporter} against PO {$shipment->salesOrder->order_number}.",
                    ]);
                }
            }

            DB::commit();

            $msg = ($wasScheduled && $isNowDispatched)
                ? "🚀 Consignment {$shipment->shipment_number} has been officially DISPATCHED with LR #{$shipment->lr_number}!"
                : "✓ Shipment {$shipment->shipment_number} updated successfully!";

            return redirect()->route('shipments.index')->with('success', $msg);
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error("Failed to update shipment #{$id}: ".$e->getMessage(), [
                'exception' => $e,
                'request' => $request->all(),
            ]);

            return back()->withInput()->with('error', 'Failed to update shipment: '.$e->getMessage());
        }
    }

    public function destroy($id)
    {
        $shipment = CommercialShipment::with(['items.salesOrderItem.salesOrder'])->findOrFail($id);

        DB::beginTransaction();
        try {
            $lrNo = $shipment->lr_number;
            $affectedOrders = [];

            // 1. RESTORE PO BALANCES
            foreach ($shipment->items as $sItem) {
                $orderItem = $sItem->salesOrderItem;
                if ($orderItem) {
                    $restoredQty = (float) $sItem->quantity;
                    $newBalance = $orderItem->balance_qty + $restoredQty;
                    $newShipped = max(0, $orderItem->shipped_qty - $restoredQty);
                    $newStatus = $newShipped <= 0 ? 'PENDING' : 'PARTIAL';

                    $orderItem->update([
                        'balance_qty' => $newBalance,
                        'shipped_qty' => $newShipped,
                        'status' => $newStatus,
                    ]);

                    $order = $orderItem->salesOrder;
                    if ($order) {
                        $affectedOrders[$order->id] = $order;
                    }
                }
            }

            // 2. Re-evaluate Sales Orders status
            foreach ($affectedOrders as $ord) {
                $remainingBal = $ord->items()->sum('balance_qty');
                $totalShipped = $ord->items()->sum('shipped_qty');
                $ordStatus = $totalShipped <= 0 ? 'CONFIRMED' : ($remainingBal <= 0 ? 'COMPLETED' : 'PARTIALLY_DISPATCHED');

                $ord->update([
                    'status' => $ordStatus,
                ]);

                ActivityLog::create([
                    'customer_id' => $ord->customer_id,
                    'activity_type' => 'SHIPMENT',
                    'title' => "Shipment Deleted & Balance Restored (LR: {$lrNo})",
                    'description' => "Cancelled consignment {$lrNo}. Restored dispatched quantities back to PO {$ord->order_number}.",
                ]);
            }

            // 3. Delete shipment items and shipment
            $shipment->items()->delete();
            $shipment->delete();

            DB::commit();

            return redirect()->route('shipments.index')->with('success', "✓ Shipment {$lrNo} has been deleted and all PO balances have been restored!");
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error("Failed to delete shipment #{$id}: ".$e->getMessage(), [
                'exception' => $e,
            ]);

            return back()->with('error', 'Failed to delete shipment: '.$e->getMessage());
        }
    }

    public function updateStatus(Request $request, $id)
    {
        $shipment = CommercialShipment::with('salesOrder')->findOrFail($id);
        $status = $request->input('status', 'DELIVERED');

        $actualDeliveryDate = $shipment->actual_delivery_date;
        if ($status === 'DELIVERED') {
            $actualDeliveryDate = Carbon::now();
            if ($request->filled('actual_delivery_date')) {
                try {
                    $rawDate = trim($request->input('actual_delivery_date'));
                    if (preg_match('/^\d{2}\/\d{2}\/\d{4}$/', $rawDate)) {
                        $actualDeliveryDate = Carbon::createFromFormat('d/m/Y', $rawDate);
                    } else {
                        $actualDeliveryDate = Carbon::parse($rawDate);
                    }
                } catch (\Throwable $e) {
                    $actualDeliveryDate = Carbon::now();
                }
            }
        }

        DB::beginTransaction();
        try {
            $shipment->update([
                'status' => $status,
                'actual_delivery_date' => $actualDeliveryDate,
            ]);

            if ($status === 'DELIVERED') {
                // Close delivery confirmation tasks
                FollowupTask::where('customer_id', $shipment->salesOrder?->customer_id)
                    ->where('status', 'PENDING')
                    ->where(function ($q) use ($shipment) {
                        $q->where('type', 'DELIVERY_CONFIRMATION')
                            ->where(function ($sq) use ($shipment) {
                                $sq->where('pending_item', 'like', "%{$shipment->lr_number}%")
                                    ->orWhere('reason', 'like', "%{$shipment->lr_number}%");
                            });
                    })
                    ->update([
                        'status' => 'COMPLETED',
                        'completed_at' => Carbon::now(),
                        'outcome_notes' => "Consignment LR {$shipment->lr_number} marked delivered.",
                    ]);
            }

            DB::commit();

            if ($request->wantsJson() || $request->ajax()) {
                return response()->json([
                    'success' => true,
                    'message' => "✓ Shipment {$shipment->lr_number} marked as {$status}!",
                    'status' => $status,
                ]);
            }

            return back()->with('success', "✓ Shipment {$shipment->lr_number} marked as {$status}!");
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error("Failed to update status on shipment #{$id}: ".$e->getMessage(), [
                'exception' => $e,
                'request' => $request->all(),
            ]);
            if ($request->wantsJson() || $request->ajax()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to update status: '.$e->getMessage(),
                ], 500);
            }

            return back()->with('error', 'Failed to update shipment status: '.$e->getMessage());
        }
    }

    /**
     * Get real-time live tracking data for a shipment
     */
    public function liveTracking(Request $request, $id)
    {
        $shipment = CommercialShipment::with(['salesOrder.customer', 'items.salesOrderItem.product'])->findOrFail($id);
        $data = TransporterTrackingService::fetchLiveTrackingDetails(
            $shipment->transporter,
            $shipment->lr_number,
            $shipment,
            $request->boolean('force')
        );

        return response()->json($data);
    }

    /**
     * Get live tracking data by query parameters
     */
    public function trackConsignmentApi(Request $request)
    {
        $transporter = $request->query('transporter');
        $lr = $request->query('lr');
        $shipmentId = $request->query('shipment_id');

        $shipment = $shipmentId ? CommercialShipment::with(['salesOrder.customer'])->find($shipmentId) : null;
        $data = TransporterTrackingService::fetchLiveTrackingDetails(
            $transporter,
            $lr,
            $shipment,
            $request->boolean('force')
        );

        return response()->json($data);
    }
}
