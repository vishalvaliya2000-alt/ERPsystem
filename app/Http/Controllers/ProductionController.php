<?php

namespace App\Http\Controllers;

use App\Models\InventoryStock;
use App\Models\Product;
use App\Models\ProductionBatch;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ProductionController extends Controller
{
    public function index()
    {
        $stocks = InventoryStock::with('product.category')->orderByDesc('current_stock_qty')->get();
        $batches = ProductionBatch::with('product')->orderByDesc('production_date')->get();
        $products = Product::where('is_active', true)->get();

        $totalStockKg = $stocks->sum('current_stock_qty');
        $lowStockCount = $stocks->filter(fn ($s) => $s->current_stock_qty <= $s->min_threshold_qty)->count();

        return view('production.index', compact('stocks', 'batches', 'products', 'totalStockKg', 'lowStockCount'));
    }

    public function storeBatch(Request $request)
    {
        $validated = $request->validate([
            'product_id' => 'required|exists:products,id',
            'batch_qty' => 'required|numeric|min:10',
            'moisture_percentage' => 'nullable|numeric',
            'sensory_grade' => 'nullable|string',
            'raw_lot_number' => 'nullable|string',
            'notes' => 'nullable|string',
        ]);

        DB::beginTransaction();
        try {
            $prod = Product::findOrFail($validated['product_id']);
            $batchNo = 'B-'.$prod->product_code.'-'.date('dmy');

            $batch = ProductionBatch::create([
                'batch_number' => $batchNo,
                'product_id' => $prod->id,
                'production_date' => Carbon::now(),
                'batch_qty' => $validated['batch_qty'],
                'available_qty' => $validated['batch_qty'],
                'moisture_percentage' => $validated['moisture_percentage'] ?? 5.0,
                'sensory_grade' => $validated['sensory_grade'] ?? 'Premium Export Grade',
                'raw_lot_number' => $validated['raw_lot_number'] ?? ('LOT-'.date('Ymd')),
                'status' => 'QUALITY_APPROVED',
                'notes' => $validated['notes'] ?? null,
            ]);

            // Add to inventory stock
            $stock = InventoryStock::firstOrCreate(['product_id' => $prod->id]);
            $stock->increment('current_stock_qty', $validated['batch_qty']);
            $stock->update(['last_audited_at' => Carbon::now()]);

            DB::commit();

            return redirect()->route('production.index')->with('success', "Batch {$batchNo} ({$batch->batch_qty} KG) registered and added to stock!");
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('Failed to create production batch: '.$e->getMessage(), [
                'exception' => $e,
                'request' => $request->all(),
            ]);

            return back()->withInput()->with('error', 'Failed to register batch: '.$e->getMessage());
        }
    }
}
