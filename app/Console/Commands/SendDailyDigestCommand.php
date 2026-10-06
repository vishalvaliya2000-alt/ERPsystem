<?php

namespace App\Console\Commands;

use App\Models\ActivityLog;
use App\Models\CommercialShipment;
use App\Models\Invoice;
use App\Models\PaymentReceipt;
use App\Models\SalesOrder;
use App\Models\Tenant;
use Carbon\Carbon;
use Illuminate\Console\Command;

class SendDailyDigestCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'erp:send-daily-digest {--tenant= : Specific Tenant ID to compile digest for}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Compile and generate 7:00 PM Evening Executive Briefing for today\'s dispatches, collections, and tomorrow\'s radar';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $tenantOpt = $this->option('tenant');

        $tenantsQuery = Tenant::where('is_active', true);
        if ($tenantOpt) {
            $tenantsQuery->where('id', $tenantOpt);
        }
        $tenants = $tenantsQuery->get();

        if ($tenants->isEmpty()) {
            $this->warn('No active tenants found to process.');

            return 0;
        }

        $this->info('🌙 Compiling 7:00 PM Evening Executive Briefing for '.$tenants->count().' active tenant(s)...');

        $today = Carbon::today();
        $tomorrow = Carbon::tomorrow();

        foreach ($tenants as $tenant) {
            $this->line("\n=======================================================");
            $this->info("🏢 {$tenant->name} — Daily Executive Digest (".$today->format('d M Y').')');
            $this->line('=======================================================');

            // 1. TODAY'S DISPATCHES
            $todayShipments = CommercialShipment::withoutGlobalScopes()
                ->with(['salesOrder.customer', 'items.salesOrderItem.product'])
                ->where('tenant_id', $tenant->id)
                ->whereDate('shipment_date', $today)
                ->get();

            $totalDispatchedKg = 0;
            $shipmentLines = [];

            foreach ($todayShipments as $shp) {
                $qty = (float) $shp->items->sum('quantity');
                $totalDispatchedKg += $qty;
                $cust = $shp->salesOrder?->customer ?? $shp->items->first()?->salesOrderItem?->salesOrder?->customer;
                $custName = $cust?->company_name ?? 'Buyer';
                $firstProd = $shp->items->first()?->salesOrderItem?->product?->product_name ?? 'Dehydrated goods';
                $lr = $shp->lr_number ?: $shp->shipment_number;
                $transporter = $shp->transporter ?: 'Direct Truck';
                $dest = $shp->destination ?: ($cust?->city ?? 'Godown');

                $shipmentLines[] = "• LR {$lr} ({$transporter}): ".number_format($qty)." KG {$firstProd} to {$dest} ({$custName})";
            }

            // 2. TODAY'S COLLECTIONS
            $todayReceipts = PaymentReceipt::withoutGlobalScopes()
                ->with('customer')
                ->where('tenant_id', $tenant->id)
                ->where('payment_mode', '!=', 'ADVANCE_OFFSET')
                ->whereDate('receipt_date', $today)
                ->get();

            $totalCollected = (float) $todayReceipts->sum('amount_received');
            $receiptLines = [];

            foreach ($todayReceipts as $rec) {
                $custName = $rec->customer?->company_name ?? 'Customer';
                $ref = $rec->reference_number ? " [Ref: {$rec->reference_number}]" : '';
                $mode = $rec->payment_mode ?: 'RTGS';
                $receiptLines[] = "• {$custName}: ".formatINR($rec->amount_received)." ({$mode}{$ref})";
            }

            // 3. TODAY'S NEW SALES ORDERS
            $todayOrders = SalesOrder::withoutGlobalScopes()
                ->with(['customer', 'items'])
                ->where('tenant_id', $tenant->id)
                ->whereDate('order_date', $today)
                ->get();

            $totalOrderValue = (float) $todayOrders->sum('total_amount');
            $totalOrderQty = 0;
            $orderLines = [];

            foreach ($todayOrders as $ord) {
                $orderQty = (float) $ord->items->sum('order_qty');
                $totalOrderQty += $orderQty;
                $poNum = $ord->po_number ?: $ord->order_number;
                $custName = $ord->customer?->company_name ?? 'Client';
                $orderLines[] = "• PO {$poNum} ({$custName}): ".formatINR($ord->total_amount).' ('.number_format($orderQty).' KG)';
            }

            // 4. TOMORROW'S RECEIVABLES RADAR
            $tomorrowInvoices = Invoice::withoutGlobalScopes()
                ->with('customer')
                ->where('tenant_id', $tenant->id)
                ->whereDate('due_date', $tomorrow)
                ->where('balance_due', '>', 0)
                ->get();

            $totalDueTomorrow = (float) $tomorrowInvoices->sum('balance_due');
            $tomorrowLines = [];

            foreach ($tomorrowInvoices as $inv) {
                $custName = $inv->customer?->company_name ?? 'Customer';
                $tomorrowLines[] = "• Inv {$inv->invoice_number} ({$custName}): ".formatINR($inv->balance_due);
            }

            // COMPOSE EXECUTIVE BRIEFING TEXT (WhatsApp-ready formatting)
            $text = "🌙 *EVENING EXECUTIVE DIGEST* — {$tenant->name}\n";
            $text .= '📅 '.$today->format('l, d F Y')." (7:00 PM Daily Briefing)\n";
            $text .= "━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";

            // Dispatches
            $text .= "🚚 *TODAY'S FACTORY DISPATCHES*\n";
            if ($todayShipments->isNotEmpty()) {
                $text .= '• Total Loaded: '.number_format($totalDispatchedKg).' KG across '.$todayShipments->count()." consignment(s)\n";
                foreach ($shipmentLines as $sl) {
                    $text .= "  {$sl}\n";
                }
            } else {
                $text .= "• Zero dispatches scheduled or loaded today.\n";
            }
            $text .= "\n";

            // Collections
            $text .= "💰 *TODAY'S CASH COLLECTIONS (RTGS/BANK)*\n";
            if ($todayReceipts->isNotEmpty()) {
                $text .= '• Total Received: '.formatINR($totalCollected).' across '.$todayReceipts->count()." receipt(s)\n";
                foreach ($receiptLines as $rl) {
                    $text .= "  {$rl}\n";
                }
            } else {
                $text .= "• No bank credits or payment receipts recorded today.\n";
            }
            $text .= "\n";

            // Sales Orders
            $text .= "📝 *NEW CONTRACTS BOOKED TODAY*\n";
            if ($todayOrders->isNotEmpty()) {
                $text .= '• Total Booked: '.formatINR($totalOrderValue).' ('.number_format($totalOrderQty)." KG)\n";
                foreach ($orderLines as $ol) {
                    $text .= "  {$ol}\n";
                }
            } else {
                $text .= "• No new sales contracts logged today.\n";
            }
            $text .= "\n";

            // Tomorrow's Radar
            $text .= "🚨 *TOMORROW'S RECEIVABLES RADAR*\n";
            if ($tomorrowInvoices->isNotEmpty()) {
                $text .= '• Total Due Tomorrow: '.formatINR($totalDueTomorrow).' ('.$tomorrowInvoices->count()." invoice(s))\n";
                foreach ($tomorrowLines as $tl) {
                    $text .= "  {$tl}\n";
                }
            } else {
                $text .= "• 0 customer invoices due tomorrow.\n";
            }
            $text .= "━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";

            // Print to console
            $this->line($text);

            // Log activity record
            ActivityLog::withoutGlobalScopes()->create([
                'tenant_id' => $tenant->id,
                'activity_type' => 'NOTE',
                'title' => '🌙 7:00 PM Evening Digest Generated',
                'description' => 'Dispatched: '.number_format($totalDispatchedKg).' KG | Collected: '.formatINR($totalCollected).' | Booked: '.formatINR($totalOrderValue),
            ]);
        }

        return 0;
    }
}
