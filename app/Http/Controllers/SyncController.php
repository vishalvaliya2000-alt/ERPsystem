<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\CommercialShipment;
use App\Models\Expense;
use App\Models\Invoice;
use App\Models\Lead;
use App\Models\SalesOrder;
use App\Models\SalesOrderItem;
use App\Services\CompanyFirmContext;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SyncController extends Controller
{
    /**
     * Ingests and processes locally-queued offline drafts from PWA clients.
     */
    public function syncDrafts(Request $request): JsonResponse
    {
        $drafts = $request->input('drafts', []);

        if (! is_array($drafts) || empty($drafts)) {
            return response()->json([
                'success' => true,
                'synced_count' => 0,
                'synced' => [],
            ]);
        }

        $synced = [];
        $firm = CompanyFirmContext::getFirm();

        foreach ($drafts as $draft) {
            $clientId = $draft['id'] ?? null;
            $type = strtoupper((string) ($draft['type'] ?? ''));
            $payload = $draft['payload'] ?? [];

            if (! is_array($payload)) {
                continue;
            }

            try {
                DB::beginTransaction();

                $syncedRecord = match ($type) {
                    'SALES_ORDER' => $this->syncSalesOrderDraft($payload, $firm?->id),
                    'LEAD' => $this->syncLeadDraft($payload),
                    'EXPENSE' => $this->syncExpenseDraft($payload, $firm?->id),
                    default => null,
                };

                DB::commit();

                if ($syncedRecord) {
                    $synced[] = [
                        'client_id' => $clientId,
                        'server_id' => $syncedRecord['id'] ?? null,
                        'type' => $type,
                        'reference' => $syncedRecord['reference'] ?? null,
                    ];
                }
            } catch (\Throwable $e) {
                DB::rollBack();
                Log::warning("[PWA Sync] Failed to sync draft {$clientId} of type {$type}: ".$e->getMessage());
            }
        }

        return response()->json([
            'success' => true,
            'synced_count' => count($synced),
            'synced' => $synced,
        ]);
    }

    /**
     * Cross-device synchronization heartbeat / pulse endpoint.
     */
    public function pulse(Request $request): JsonResponse
    {
        $firm = CompanyFirmContext::getFirm();

        $pendingOrders = SalesOrder::where('status', 'PENDING')->count();
        $dispatchedShipments = CommercialShipment::where('status', 'DISPATCHED')->count();
        $overdueInvoices = Invoice::where('status', 'OVERDUE')->count();

        return response()->json([
            'server_time' => Carbon::now()->toIso8601String(),
            'firm_id' => $firm?->id,
            'firm_name' => $firm?->firm_name,
            'kpis' => [
                'pending_orders' => $pendingOrders,
                'in_transit_shipments' => $dispatchedShipments,
                'overdue_invoices' => $overdueInvoices,
            ],
            'status' => 'online',
        ]);
    }

    /**
     * Create Sales Order draft from offline payload.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    protected function syncSalesOrderDraft(array $payload, ?int $firmId): array
    {
        $orderNumber = $payload['order_number'] ?? 'SO-OFFLINE-'.strtoupper(substr(bin2hex(random_bytes(3)), 0, 6));

        $total = (float) ($payload['grand_total'] ?? $payload['total_amount'] ?? 0.0);

        $order = SalesOrder::create([
            'order_number' => $orderNumber,
            'firm_id' => $firmId,
            'customer_id' => $payload['customer_id'] ?? null,
            'order_date' => $payload['order_date'] ?? Carbon::now(),
            'currency' => $payload['currency'] ?? 'INR',
            'subtotal' => (float) ($payload['subtotal'] ?? 0.0),
            'tax_amount' => (float) ($payload['tax_total'] ?? $payload['tax_amount'] ?? 0.0),
            'total_amount' => $total,
            'balance_amount' => $total,
            'status' => 'DRAFT',
            'notes' => 'Created via PWA Offline Queue on '.Carbon::now()->format('Y-m-d H:i'),
        ]);

        if (! empty($payload['items']) && is_array($payload['items'])) {
            foreach ($payload['items'] as $item) {
                SalesOrderItem::create([
                    'sales_order_id' => $order->id,
                    'product_id' => $item['product_id'] ?? null,
                    'order_qty' => (float) ($item['quantity'] ?? 1.0),
                    'shipped_qty' => 0.0,
                    'balance_qty' => (float) ($item['quantity'] ?? 1.0),
                    'rate' => (float) ($item['rate'] ?? 0.0),
                    'order_value' => (float) ($item['total'] ?? 0.0),
                ]);
            }
        }

        return [
            'id' => $order->id,
            'reference' => $order->order_number,
        ];
    }

    /**
     * Create Lead draft from offline payload.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    protected function syncLeadDraft(array $payload): array
    {
        $lead = Lead::create([
            'company_name' => $payload['company_name'] ?? 'Offline Captured Lead',
            'contact_person' => $payload['contact_person'] ?? null,
            'phone' => $payload['phone'] ?? null,
            'whatsapp' => $payload['whatsapp'] ?? null,
            'email' => $payload['email'] ?? null,
            'city' => $payload['city'] ?? null,
            'state' => $payload['state'] ?? 'Domestic',
            'country' => $payload['country'] ?? 'India',
            'source' => 'PWA Offline Field App',
            'estimated_value' => (float) ($payload['estimated_value'] ?? 0.0),
            'stage' => 'NEW',
            'priority' => 'MEDIUM',
            'notes' => 'Captured offline via PWA on '.Carbon::now()->format('Y-m-d H:i'),
        ]);

        return [
            'id' => $lead->id,
            'reference' => $lead->company_name,
        ];
    }

    /**
     * Create Expense draft from offline payload.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    protected function syncExpenseDraft(array $payload, ?int $firmId): array
    {
        $base = (float) ($payload['base_amount'] ?? 0.0);
        $rate = (float) ($payload['gst_rate'] ?? 0.0);
        $tax = round(($base * $rate) / 100, 2);

        $expense = Expense::create([
            'firm_id' => $firmId,
            'vendor_id' => $payload['vendor_id'] ?? null,
            'expense_category' => $payload['expense_category'] ?? 'Operational Supplies',
            'vendor_invoice_number' => $payload['vendor_invoice_number'] ?? 'OFFLINE-'.time(),
            'expense_date' => $payload['expense_date'] ?? Carbon::today(),
            'base_amount' => $base,
            'gst_rate' => $rate,
            'cgst_amount' => round($tax / 2, 2),
            'sgst_amount' => round($tax / 2, 2),
            'igst_amount' => 0.0,
            'total_amount' => $base + $tax,
            'is_itc_eligible' => (bool) ($payload['is_itc_eligible'] ?? true),
            'itc_type' => 'ALL_OTHER_ITC',
            'payment_status' => 'PENDING',
            'notes' => 'Recorded offline via PWA',
        ]);

        return [
            'id' => $expense->id,
            'reference' => $expense->vendor_invoice_number,
        ];
    }
}
