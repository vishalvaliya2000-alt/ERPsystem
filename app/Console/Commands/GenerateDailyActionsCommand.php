<?php

namespace App\Console\Commands;

use App\Models\CommercialShipment;
use App\Models\FollowupTask;
use App\Models\Invoice;
use App\Models\SalesOrder;
use App\Models\SalesOrderItem;
use App\Models\Sample;
use App\Models\Tenant;
use Carbon\Carbon;
use Illuminate\Console\Command;

class GenerateDailyActionsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'erp:generate-daily-actions {--tenant= : Specific Tenant ID to process} {--wipe-all : Wipe all previous tasks including manual notes}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Clean up previous follow-ups and scan live daily transactions to generate Today\'s Priority Decision Queue across all tenants';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $tenantOpt = $this->option('tenant');
        $wipeAll = $this->option('wipe-all');

        $tenantsQuery = Tenant::where('is_active', true);
        if ($tenantOpt) {
            $tenantsQuery->where('id', $tenantOpt);
        }
        $tenants = $tenantsQuery->get();

        if ($tenants->isEmpty()) {
            $this->warn('No active tenants found to process.');

            return 0;
        }

        $this->info('🚀 Starting Daily Priority Decision Queue Engine for '.$tenants->count().' active tenant(s)...');

        $totalCleaned = 0;
        $totalCreated = 0;

        foreach ($tenants as $tenant) {
            $this->line("\n=======================================================");
            $this->info("🏢 Processing Tenant #{$tenant->id}: {$tenant->name}");
            $this->line('=======================================================');

            // 1. CLEAN UP PREVIOUS AUTO-GENERATED FOLLOW-UP & ACTION DATA
            $cleanupQuery = FollowupTask::withoutGlobalScopes()->where('tenant_id', $tenant->id);
            if (! $wipeAll) {
                // Keep manual user notes if any, wipe all auto-generated system actions
                $cleanupQuery->whereIn('type', [
                    'PAYMENT_FOLLOWUP',
                    'SALES_FOLLOWUP',
                    'DELIVERY_CONFIRMATION',
                    'SAMPLE_EVALUATION',
                    'DISPATCH_REMINDER',
                ]);
            }
            $cleanedCount = $cleanupQuery->delete();
            $totalCleaned += $cleanedCount;
            $this->comment("  🧹 Cleaned {$cleanedCount} previous follow-up action(s).");

            $createdForTenant = 0;
            $today = Carbon::today();

            // 2. SCAN OVERDUE & UPCOMING PAYMENT INVOICES
            $unpaidInvoices = Invoice::withoutGlobalScopes()
                ->with('customer')
                ->where('tenant_id', $tenant->id)
                ->where('balance_due', '>', 0)
                ->orderBy('due_date')
                ->get();

            foreach ($unpaidInvoices as $inv) {
                $dueDate = $inv->due_date ? Carbon::parse($inv->due_date)->startOfDay() : $today;
                $daysOverdue = $today->diffInDays($dueDate, false); // negative if overdue

                $isOverdue = $dueDate->lt($today);
                $isDueSoon = $dueDate->gte($today) && $dueDate->lte($today->copy()->addDays(2));

                if ($isOverdue || $isDueSoon) {
                    $overdueDaysCount = abs($daysOverdue);
                    $priority = ($isOverdue && $overdueDaysCount > 7) ? 'URGENT' : ($isOverdue ? 'HIGH' : 'MEDIUM');

                    $custName = $inv->customer?->company_name ?? 'Client';
                    $contact = $inv->customer?->primary_contact_person ?: ($inv->customer?->contact_person ?: 'Sir');
                    $phone = $inv->customer?->primary_phone ?: ($inv->customer?->phone ?: '');

                    $reasonText = $isOverdue
                        ? "Payment follow-up for Tax Invoice {$inv->invoice_number} (".formatINR($inv->balance_due).") - {$overdueDaysCount}d overdue"
                        : "Payment due soon: Tax Invoice {$inv->invoice_number} (".formatINR($inv->balance_due).')';

                    $nextAction = 'Send WhatsApp invoice reminder and check expected RTGS payment date';

                    FollowupTask::withoutGlobalScopes()->create([
                        'tenant_id' => $tenant->id,
                        'customer_id' => $inv->customer_id,
                        'related_entity_type' => 'INVOICE',
                        'related_entity_id' => $inv->id,
                        'type' => 'PAYMENT_FOLLOWUP',
                        'reason' => $reasonText,
                        'priority' => $priority,
                        'due_date' => $dueDate,
                        'status' => 'PENDING',
                        'pending_item' => "Invoice {$inv->invoice_number} balance ".formatINR($inv->balance_due),
                        'next_action' => $nextAction,
                    ]);

                    $createdForTenant++;
                }
            }

            // 3. SCAN IN-TRANSIT SHIPMENTS (CONSIGNMENTS)
            $activeShipments = CommercialShipment::withoutGlobalScopes()
                ->with(['salesOrder.customer', 'items.salesOrderItem.product'])
                ->where('tenant_id', $tenant->id)
                ->whereIn('status', ['DISPATCHED', 'IN_TRANSIT', 'PICKUP_PENDING'])
                ->orderBy('shipment_date')
                ->get();

            foreach ($activeShipments as $shp) {
                $shipDate = $shp->shipment_date ? Carbon::parse($shp->shipment_date)->startOfDay() : $today;
                $daysInTransit = $shipDate->diffInDays($today);

                $cust = $shp->salesOrder?->customer ?? $shp->items->first()?->salesOrderItem?->salesOrder?->customer;
                $custName = $cust?->company_name ?? 'Customer';
                $lrNumber = $shp->lr_number ?: $shp->shipment_number;
                $transporter = $shp->transporter ?: 'Transporter';

                $reasonText = "Track delivery & obtain POD for LR {$lrNumber} ({$transporter}) consigned to {$custName} ({$daysInTransit}d in transit)";
                $priority = ($daysInTransit >= 3) ? 'HIGH' : 'MEDIUM';

                FollowupTask::withoutGlobalScopes()->create([
                    'tenant_id' => $tenant->id,
                    'customer_id' => $cust?->id,
                    'related_entity_type' => 'SHIPMENT',
                    'related_entity_id' => $shp->id,
                    'type' => 'DELIVERY_CONFIRMATION',
                    'reason' => $reasonText,
                    'priority' => $priority,
                    'due_date' => $today,
                    'status' => 'PENDING',
                    'pending_item' => "LR {$lrNumber} ({$shp->destination})",
                    'next_action' => "Check delivery with {$transporter} tracking and confirm material receipt with buyer godown",
                ]);

                $createdForTenant++;
            }

            // 4. SCAN OPEN SALES ORDERS & DISPATCH BOTTLENECKS
            // Generated by default for open orders, UNLESS remarks/notes indicate dispatches are as per client requirement / buyer will update.
            $openOrders = SalesOrder::withoutGlobalScopes()
                ->with('customer')
                ->where('tenant_id', $tenant->id)
                ->whereNotIn('status', ['COMPLETED', 'CANCELLED', 'DELIVERED'])
                ->get();

            foreach ($openOrders as $order) {
                $orderItems = SalesOrderItem::withoutGlobalScopes()->where('sales_order_id', $order->id)->get();
                $pendingQty = (float) $orderItems->sum('balance_qty');
                if ($pendingQty <= 0) {
                    continue;
                }

                // Check remarks on order, customer, and completed tasks
                $combinedRemarks = strtolower(($order->notes ?? '').' '.($order->customer?->notes ?? ''));

                $hasClientRequirementRemark =
                    str_contains($combinedRemarks, 'as per client requirement') ||
                    str_contains($combinedRemarks, 'per client requirement') ||
                    str_contains($combinedRemarks, 'client requirement') ||
                    str_contains($combinedRemarks, 'buyer requirement') ||
                    str_contains($combinedRemarks, 'as per requirement') ||
                    str_contains($combinedRemarks, 'client will update') ||
                    str_contains($combinedRemarks, 'buyer will update') ||
                    str_contains($combinedRemarks, 'client will connect') ||
                    str_contains($combinedRemarks, 'buyer will connect') ||
                    str_contains($combinedRemarks, 'connect from their end') ||
                    str_contains($combinedRemarks, 'on demand') ||
                    str_contains($combinedRemarks, 'on-demand') ||
                    str_contains($combinedRemarks, 'call off') ||
                    str_contains($combinedRemarks, 'call-off') ||
                    str_contains($combinedRemarks, 'hold until buyer update') ||
                    str_contains($combinedRemarks, 'no reminder') ||
                    str_contains($combinedRemarks, 'no follow');

                // If user/client remarked that dispatches will be as per client requirement, do NOT create daily reminder
                if ($hasClientRequirementRemark) {
                    continue;
                }

                $orderDate = $order->order_date ? Carbon::parse($order->order_date)->startOfDay() : $today;
                $daysSinceContract = $orderDate->diffInDays($today);

                $poNumber = $order->po_number ?: $order->order_number;
                $clientDisplayName = $order->customer?->company_name ?? 'Client';

                $reasonText = "Dispatch fulfillment pending for PO {$poNumber} ({$clientDisplayName}) - ".number_format($pendingQty).' KG remaining';
                $priority = ($daysSinceContract >= 7) ? 'HIGH' : 'MEDIUM';

                FollowupTask::withoutGlobalScopes()->create([
                    'tenant_id' => $tenant->id,
                    'customer_id' => $order->customer_id,
                    'related_entity_type' => 'SALES_ORDER',
                    'related_entity_id' => $order->id,
                    'type' => 'SALES_FOLLOWUP',
                    'reason' => $reasonText,
                    'priority' => $priority,
                    'due_date' => $today,
                    'status' => 'PENDING',
                    'pending_item' => "PO {$poNumber} (".number_format($pendingQty).' KG pending)',
                    'next_action' => 'Check factory finished goods stock or confirm delivery schedule with buyer',
                ]);

                $createdForTenant++;
            }

            // 5. SCAN SAMPLE EVALUATIONS & TRIALS
            $pendingSamples = Sample::withoutGlobalScopes()
                ->with(['customer', 'product'])
                ->where('tenant_id', $tenant->id)
                ->whereIn('trial_status', ['Pending', 'Pending Evaluation', 'In Trial'])
                ->get();

            foreach ($pendingSamples as $samp) {
                $custName = $samp->customer?->company_name ?? 'Prospect';
                $prodName = $samp->product?->product_name ?? 'Product Sample';

                FollowupTask::withoutGlobalScopes()->create([
                    'tenant_id' => $tenant->id,
                    'customer_id' => $samp->customer_id,
                    'related_entity_type' => 'SAMPLE',
                    'related_entity_id' => $samp->id,
                    'type' => 'SAMPLE_EVALUATION',
                    'reason' => "Sample trial feedback pending: {$prodName} sent to {$custName} ({$samp->sample_number})",
                    'priority' => 'MEDIUM',
                    'due_date' => $today,
                    'status' => 'PENDING',
                    'pending_item' => "Sample {$samp->sample_number}",
                    'next_action' => 'Call buyer QC / R&D team to capture moisture/taste evaluation test results',
                ]);

                $createdForTenant++;
            }

            $totalCreated += $createdForTenant;
            $this->info("  ✅ Generated {$createdForTenant} fresh priority action(s) for {$tenant->name}.");
        }

        $this->line("\n=======================================================");
        $this->info('🎯 Daily Action Generation Complete!');
        $this->info("   - Total Old Actions Cleaned: {$totalCleaned}");
        $this->info("   - Total Fresh Actions Generated: {$totalCreated}");
        $this->line("=======================================================\n");

        return 0;
    }
}
