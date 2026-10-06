<?php

namespace App\Console\Commands;

use App\Models\Invoice;
use App\Models\PaymentReminderLog;
use App\Models\Tenant;
use App\Services\WhatsApp\WhatsAppService;
use Carbon\Carbon;
use Illuminate\Console\Command;

class SendAutomatedPaymentReminders extends Command
{
    protected $signature = 'reminders:send-payment {--tenant= : Specific tenant to process}';

    protected $description = 'Scans unpaid invoices across tenants and sends automated payment reminders (before due, on due, and overdue cadence)';

    public function handle(WhatsAppService $whatsAppService): int
    {
        $this->info('Starting automated payment reminders scan...');

        if (tenancy()->initialized) {
            $sent = $this->processTenantInvoices($whatsAppService);
            $this->info("Processed current tenant. Sent: {$sent} reminders.");

            return Command::SUCCESS;
        }

        $tenantId = $this->option('tenant');
        $tenantsQuery = Tenant::query();
        if ($tenantId) {
            $tenantsQuery->where('id', $tenantId);
        }

        $totalSent = 0;
        foreach ($tenantsQuery->cursor() as $tenant) {
            $tenant->run(function () use (&$totalSent, $whatsAppService) {
                $count = $this->processTenantInvoices($whatsAppService);
                $totalSent += $count;
            });
        }

        $this->info("Payment reminder cycle completed. Total reminders sent: {$totalSent}.");

        return Command::SUCCESS;
    }

    /**
     * Evaluates all open invoices in the current tenant and sends reminders.
     */
    public function processTenantInvoices(WhatsAppService $whatsAppService): int
    {
        $today = Carbon::today();
        $sentCount = 0;

        $invoices = Invoice::with(['customer', 'firm'])
            ->where('balance_due', '>', 0)
            ->whereNotIn('status', ['PAID', 'CANCELLED', 'DRAFT'])
            ->whereNotNull('due_date')
            ->get();

        foreach ($invoices as $inv) {
            $customer = $inv->customer;
            if (! $customer) {
                continue;
            }

            $dueDate = Carbon::parse($inv->due_date)->startOfDay();

            // 1. SCENARIO A: Exactly 3 days before due date
            if ($today->copy()->addDays(3)->isSameDay($dueDate)) {
                $alreadySent = PaymentReminderLog::where('invoice_id', $inv->id)
                    ->where('reminder_type', 'BEFORE_DUE')
                    ->where('sent_at', '>=', $today->copy()->subDays(4))
                    ->exists();

                if (! $alreadySent) {
                    $res = $whatsAppService->sendPaymentReminder($customer, $inv, 'BEFORE_DUE');
                    if ($res['success']) {
                        $sentCount++;
                    }
                }
            }

            // 2. SCENARIO B: Due Today
            elseif ($today->isSameDay($dueDate)) {
                $alreadySent = PaymentReminderLog::where('invoice_id', $inv->id)
                    ->where('reminder_type', 'ON_DUE')
                    ->whereDate('sent_at', $today)
                    ->exists();

                if (! $alreadySent) {
                    $res = $whatsAppService->sendPaymentReminder($customer, $inv, 'ON_DUE');
                    if ($res['success']) {
                        $sentCount++;
                    }
                }
            }

            // 3. SCENARIO C: Overdue (Cadence: Every 5 days overdue)
            elseif ($today->greaterThan($dueDate)) {
                $daysOverdue = (int) $dueDate->diffInDays($today);

                // Check last sent overdue reminder
                $lastOverdueLog = PaymentReminderLog::where('invoice_id', $inv->id)
                    ->where('reminder_type', 'OVERDUE')
                    ->orderBy('sent_at', 'desc')
                    ->first();

                $daysSinceLastReminder = $lastOverdueLog ? Carbon::parse($lastOverdueLog->sent_at)->diffInDays($today) : 999;

                // Send if overdue by at least 1 day and either no reminder sent yet or last reminder was >= 5 days ago
                if ($daysSinceLastReminder >= 5) {
                    $res = $whatsAppService->sendPaymentReminder($customer, $inv, 'OVERDUE', $daysOverdue);
                    if ($res['success']) {
                        $sentCount++;
                    }
                }
            }
        }

        return $sentCount;
    }
}
