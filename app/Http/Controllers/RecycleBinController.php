<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\CommercialShipment;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Product;
use App\Models\Quotation;
use App\Models\SalesOrder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class RecycleBinController extends Controller
{
    /**
     * Map of supported model keys to Eloquent classes.
     *
     * @var array<string, class-string>
     */
    protected static array $modelMap = [
        'customer' => Customer::class,
        'product' => Product::class,
        'sales_order' => SalesOrder::class,
        'commercial_shipment' => CommercialShipment::class,
        'invoice' => Invoice::class,
        'quotation' => Quotation::class,
    ];

    /**
     * Display soft-deleted records across all primary ERP modules.
     */
    public function index()
    {
        $trashedCustomers = Customer::onlyTrashed()->orderByDesc('deleted_at')->get();
        $trashedProducts = Product::onlyTrashed()->with('category')->orderByDesc('deleted_at')->get();
        $trashedOrders = SalesOrder::onlyTrashed()->with('customer')->orderByDesc('deleted_at')->get();
        $trashedShipments = CommercialShipment::onlyTrashed()->with('salesOrder.customer')->orderByDesc('deleted_at')->get();
        $trashedInvoices = Invoice::onlyTrashed()->with('customer')->orderByDesc('deleted_at')->get();
        $trashedQuotations = Quotation::onlyTrashed()->with('customer')->orderByDesc('deleted_at')->get();

        $totalTrashedCount = $trashedCustomers->count() +
            $trashedProducts->count() +
            $trashedOrders->count() +
            $trashedShipments->count() +
            $trashedInvoices->count() +
            $trashedQuotations->count();

        return view('organization.recycle-bin', compact(
            'trashedCustomers',
            'trashedProducts',
            'trashedOrders',
            'trashedShipments',
            'trashedInvoices',
            'trashedQuotations',
            'totalTrashedCount'
        ));
    }

    /**
     * Restore a soft-deleted record.
     */
    public function restore(Request $request)
    {
        $user = auth()->user();
        if (! $user || (! $user->can('recycle_bin.restore') && ! in_array($user->role, ['owner', 'admin']))) {
            abort(403, 'Unauthorized: You do not have permission to restore deleted records.');
        }

        $validated = $request->validate([
            'model_type' => 'required|string|in:customer,product,sales_order,commercial_shipment,invoice,quotation',
            'id' => 'required|integer',
        ]);

        $modelClass = static::$modelMap[$validated['model_type']];
        $record = $modelClass::onlyTrashed()->findOrFail($validated['id']);

        DB::beginTransaction();
        try {
            $record->restore();

            $identifier = match ($validated['model_type']) {
                'customer' => $record->company_name ?? $record->customer_code,
                'product' => $record->product_name.' ('.$record->product_code.')',
                'sales_order' => $record->order_number,
                'commercial_shipment' => 'LR-'.$record->lr_number,
                'invoice' => $record->invoice_number,
                'quotation' => $record->quotation_number,
                default => '#'.$record->id,
            };

            ActivityLog::create([
                'tenant_id' => $record->tenant_id ?? null,
                'activity_type' => 'NOTE',
                'title' => "Restored Deleted Record ({$identifier})",
                'description' => 'Restored soft-deleted '.ucwords(str_replace('_', ' ', $validated['model_type']))." '{$identifier}' by user {$user->name}.",
            ]);

            DB::commit();

            return back()->with('success', "✓ Successfully restored {$identifier} from Recycle Bin!");
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('Failed to restore trashed item: '.$e->getMessage(), [
                'exception' => $e,
                'request' => $request->all(),
            ]);

            return back()->with('error', 'Failed to restore record: '.$e->getMessage());
        }
    }

    /**
     * Permanently purge a soft-deleted record.
     */
    public function forceDelete(Request $request)
    {
        $user = auth()->user();
        if (! $user || ! in_array($user->role, ['owner', 'admin'])) {
            abort(403, 'Unauthorized: Only system administrators can permanently delete records.');
        }

        $validated = $request->validate([
            'model_type' => 'required|string|in:customer,product,sales_order,commercial_shipment,invoice,quotation',
            'id' => 'required|integer',
        ]);

        $modelClass = static::$modelMap[$validated['model_type']];
        $record = $modelClass::onlyTrashed()->findOrFail($validated['id']);

        DB::beginTransaction();
        try {
            $identifier = match ($validated['model_type']) {
                'customer' => $record->company_name ?? $record->customer_code,
                'product' => $record->product_name.' ('.$record->product_code.')',
                'sales_order' => $record->order_number,
                'commercial_shipment' => 'LR-'.$record->lr_number,
                'invoice' => $record->invoice_number,
                'quotation' => $record->quotation_number,
                default => '#'.$record->id,
            };

            $record->forceDelete();

            ActivityLog::create([
                'tenant_id' => $record->tenant_id ?? null,
                'activity_type' => 'NOTE',
                'title' => "Permanently Purged Record ({$identifier})",
                'description' => 'Permanently purged '.ucwords(str_replace('_', ' ', $validated['model_type']))." '{$identifier}' from database by user {$user->name}.",
            ]);

            DB::commit();

            return back()->with('success', "✓ Permanently deleted {$identifier} from Recycle Bin.");
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('Failed to permanently delete item: '.$e->getMessage(), [
                'exception' => $e,
                'request' => $request->all(),
            ]);

            return back()->with('error', 'Failed to permanently delete record: '.$e->getMessage());
        }
    }
}
