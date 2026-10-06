<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Lead;
use App\Models\Product;
use App\Models\Sample;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SampleController extends Controller
{
    public function index()
    {
        $samples = Sample::with(['customer', 'product'])
            ->orderByDesc('created_at')
            ->get();

        $customers = Customer::all();
        $leads = Lead::all();
        $products = Product::where('is_active', true)->get();

        return view('samples.index', compact('samples', 'customers', 'leads', 'products'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'customer_id' => 'nullable|exists:customers,id',
            'product_id' => 'required|exists:products,id',
            'quantity' => 'required|numeric|min:0.01',
            'uom' => 'nullable|string|max:20',
            'courier_provider' => 'nullable|string|max:100',
            'awb_number' => 'nullable|string|max:100',
            'remarks' => 'nullable|string',
        ]);

        $sampleNumber = 'SMP-'.date('Y').'-'.rand(100, 999);

        DB::beginTransaction();
        try {
            $sample = Sample::create([
                'sample_number' => $sampleNumber,
                'customer_id' => $validated['customer_id'] ?? null,
                'product_id' => $validated['product_id'],
                'quantity' => $validated['quantity'],
                'uom' => $validated['uom'] ?: 'GMS',
                'courier_provider' => $validated['courier_provider'] ?: 'DTDC Express',
                'awb_number' => $validated['awb_number'] ?? null,
                'delivery_status' => 'IN_TRANSIT',
                'remarks' => $validated['remarks'] ?? null,
                'next_action_date' => Carbon::today()->addDays(5),
            ]);

            DB::commit();

            return back()->with('success', "✓ Sample {$sample->sample_number} dispatched successfully!");
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('Failed to create sample dispatch: '.$e->getMessage(), [
                'exception' => $e,
                'request' => $request->all(),
            ]);

            return back()->withInput()->with('error', 'Failed to dispatch sample: '.$e->getMessage());
        }
    }

    public function update(Request $request, $id)
    {
        $sample = Sample::findOrFail($id);

        $validated = $request->validate([
            'delivery_status' => 'nullable|string',
            'feedback_rating' => 'nullable|integer|min:1|max:5',
            'customer_feedback' => 'nullable|string',
            'trial_result' => 'nullable|string',
            'courier_provider' => 'nullable|string|max:100',
            'awb_number' => 'nullable|string|max:100',
        ]);

        DB::beginTransaction();
        try {
            $sample->update($validated);
            DB::commit();

            return back()->with('success', "✓ Sample {$sample->sample_number} updated successfully!");
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error("Failed to update sample #{$id}: ".$e->getMessage(), [
                'exception' => $e,
                'request' => $request->all(),
            ]);

            return back()->withInput()->with('error', 'Failed to update sample: '.$e->getMessage());
        }
    }

    public function updateStatus(Request $request, $id)
    {
        $sample = Sample::findOrFail($id);

        $validated = $request->validate([
            'delivery_status' => 'nullable|string',
            'trial_status' => 'nullable|string',
            'customer_feedback' => 'nullable|string',
        ]);

        DB::beginTransaction();
        try {
            $sample->update($validated);
            DB::commit();

            if ($request->wantsJson()) {
                return response()->json([
                    'success' => true,
                    'sample' => $sample,
                    'message' => "Sample {$sample->sample_number} status updated.",
                ]);
            }

            return back()->with('success', "✓ Sample {$sample->sample_number} status updated successfully!");
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error("Failed to update status for sample #{$id}: ".$e->getMessage(), [
                'exception' => $e,
                'request' => $request->all(),
            ]);
            if ($request->wantsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to update sample status: '.$e->getMessage(),
                ], 500);
            }

            return back()->with('error', 'Failed to update sample status: '.$e->getMessage());
        }
    }

    public function destroy($id)
    {
        $sample = Sample::findOrFail($id);
        $num = $sample->sample_number;

        DB::beginTransaction();
        try {
            $sample->delete();
            DB::commit();

            return back()->with('success', "✓ Sample {$num} deleted successfully.");
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error("Failed to delete sample #{$id}: ".$e->getMessage(), [
                'exception' => $e,
            ]);

            return back()->with('error', 'Failed to delete sample: '.$e->getMessage());
        }
    }
}
