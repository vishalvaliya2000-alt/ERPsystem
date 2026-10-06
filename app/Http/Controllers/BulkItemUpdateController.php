<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\StreamedResponse;

class BulkItemUpdateController extends Controller
{
    /**
     * Show bulk update workspace with inline grid editor and CSV import.
     */
    public function index()
    {
        $products = Product::with('category')->orderBy('product_code')->get();
        $previewData = session('bulk_preview_data', null);

        return view('products.bulk-update', compact('products', 'previewData'));
    }

    /**
     * Download products catalog as CSV template for bulk updating.
     */
    public function export(): StreamedResponse
    {
        $products = Product::orderBy('product_code')->get();
        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="products_bulk_update_'.date('Ymd').'.csv"',
        ];

        $callback = function () use ($products) {
            $file = fopen('php://output', 'w');
            fputcsv($file, [
                'product_code',
                'product_name',
                'standard_rate',
                'standard_cost',
                'hsn_code',
                'packaging',
                'min_order_qty',
                'is_active',
            ]);

            foreach ($products as $p) {
                fputcsv($file, [
                    $p->product_code,
                    $p->product_name,
                    $p->standard_rate,
                    $p->standard_cost,
                    $p->hsn_code,
                    $p->packaging,
                    $p->min_order_qty,
                    $p->is_active ? '1' : '0',
                ]);
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    /**
     * Parse uploaded CSV file and generate a preview diff before confirming changes.
     */
    public function preview(Request $request)
    {
        $request->validate([
            'csv_file' => 'required|file|mimes:csv,txt|max:5120',
        ]);

        $file = $request->file('csv_file');
        $handle = fopen($file->getRealPath(), 'r');
        if (! $handle) {
            return back()->with('error', 'Unable to open uploaded CSV file.');
        }

        $header = fgetcsv($handle);
        if (! $header) {
            fclose($handle);

            return back()->with('error', 'CSV file appears to be empty.');
        }

        // Normalize header keys
        $header = array_map(fn ($h) => strtolower(trim(str_replace([' ', '-'], '_', $h))), $header);

        $changes = [];
        $unchangedCount = 0;
        $invalidRows = [];
        $rowNum = 1;

        while (($row = fgetcsv($handle)) !== false) {
            $rowNum++;
            if (empty(array_filter($row))) {
                continue;
            }

            $rowData = array_combine($header, array_pad($row, count($header), null));
            $sku = strtoupper(trim($rowData['product_code'] ?? ''));

            if (empty($sku)) {
                $invalidRows[] = "Row {$rowNum}: Missing product_code";

                continue;
            }

            $product = Product::where('product_code', $sku)->first();
            if (! $product) {
                $invalidRows[] = "Row {$rowNum}: SKU '{$sku}' not found in database";

                continue;
            }

            $rowDiff = [
                'id' => $product->id,
                'sku' => $sku,
                'name' => $product->product_name,
                'diffs' => [],
                'new_values' => [],
            ];

            // Compare Rate
            if (isset($rowData['standard_rate']) && is_numeric($rowData['standard_rate'])) {
                $newRate = round((float) $rowData['standard_rate'], 2);
                $oldRate = (float) $product->standard_rate;
                if (abs($newRate - $oldRate) > 0.001) {
                    $rowDiff['diffs']['standard_rate'] = ['old' => $oldRate, 'new' => $newRate];
                    $rowDiff['new_values']['standard_rate'] = $newRate;
                }
            }

            // Compare Cost
            if (isset($rowData['standard_cost']) && is_numeric($rowData['standard_cost'])) {
                $newCost = round((float) $rowData['standard_cost'], 2);
                $oldCost = (float) $product->standard_cost;
                if (abs($newCost - $oldCost) > 0.001) {
                    $rowDiff['diffs']['standard_cost'] = ['old' => $oldCost, 'new' => $newCost];
                    $rowDiff['new_values']['standard_cost'] = $newCost;
                }
            }

            // Compare HSN
            if (isset($rowData['hsn_code']) && ! empty($rowData['hsn_code'])) {
                $newHsn = trim($rowData['hsn_code']);
                if ($newHsn !== $product->hsn_code) {
                    $rowDiff['diffs']['hsn_code'] = ['old' => $product->hsn_code, 'new' => $newHsn];
                    $rowDiff['new_values']['hsn_code'] = $newHsn;
                }
            }

            // Compare Packaging
            if (isset($rowData['packaging']) && ! empty($rowData['packaging'])) {
                $newPack = trim($rowData['packaging']);
                if ($newPack !== $product->packaging) {
                    $rowDiff['diffs']['packaging'] = ['old' => $product->packaging, 'new' => $newPack];
                    $rowDiff['new_values']['packaging'] = $newPack;
                }
            }

            if (! empty($rowDiff['diffs'])) {
                $changes[] = $rowDiff;
            } else {
                $unchangedCount++;
            }
        }
        fclose($handle);

        $previewData = [
            'changes' => $changes,
            'unchanged_count' => $unchangedCount,
            'invalid_rows' => $invalidRows,
            'total_processed' => count($changes) + $unchangedCount + count($invalidRows),
        ];

        session(['bulk_preview_data' => $previewData]);

        return redirect()->route('products.bulk-update.index')
            ->with('info', 'Preview ready: Found '.count($changes)." products to update, {$unchangedCount} unchanged, and ".count($invalidRows).' errors.');
    }

    /**
     * Apply confirmed bulk changes from the session preview.
     */
    public function confirm(Request $request)
    {
        $previewData = session('bulk_preview_data');
        if (! $previewData || empty($previewData['changes'])) {
            return redirect()->route('products.bulk-update.index')->with('error', 'No pending updates found to apply.');
        }

        DB::beginTransaction();
        try {
            $updatedCount = 0;
            foreach ($previewData['changes'] as $change) {
                $product = Product::find($change['id']);
                if (! $product) {
                    continue;
                }

                $product->update($change['new_values']);
                $updatedCount++;

                $diffDescriptions = [];
                foreach ($change['diffs'] as $field => $d) {
                    $diffDescriptions[] = "{$field}: {$d['old']} -> {$d['new']}";
                }

                ActivityLog::create([
                    'tenant_id' => $product->tenant_id,
                    'activity_type' => 'NOTE',
                    'title' => "Bulk Item Updated ({$product->product_code})",
                    'description' => 'Updated '.implode(', ', $diffDescriptions).' via CSV bulk upload.',
                ]);
            }

            DB::commit();
            session()->forget('bulk_preview_data');

            return redirect()->route('products.bulk-update.index')
                ->with('success', "✓ Successfully applied bulk updates to {$updatedCount} products!");
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('Failed to confirm bulk item updates: '.$e->getMessage(), ['exception' => $e]);

            return back()->with('error', 'Failed to apply bulk updates: '.$e->getMessage());
        }
    }

    /**
     * Cancel/dismiss the current CSV preview.
     */
    public function cancelPreview()
    {
        session()->forget('bulk_preview_data');

        return redirect()->route('products.bulk-update.index')->with('info', 'Bulk update preview dismissed.');
    }

    /**
     * Batch update multiple products via the inline grid editor.
     */
    public function gridUpdate(Request $request)
    {
        $validated = $request->validate([
            'products' => 'required|array|min:1',
            'products.*.id' => 'required|exists:products,id',
            'products.*.standard_rate' => 'nullable|numeric|min:0',
            'products.*.standard_cost' => 'nullable|numeric|min:0',
            'products.*.hsn_code' => 'nullable|string|max:20',
            'products.*.min_order_qty' => 'nullable|numeric|min:0',
            'products.*.packaging' => 'nullable|string|max:100',
        ]);

        DB::beginTransaction();
        try {
            $updatedCount = 0;
            foreach ($validated['products'] as $pData) {
                $product = Product::find($pData['id']);
                if ($product) {
                    $product->update([
                        'standard_rate' => $pData['standard_rate'] ?? $product->standard_rate,
                        'standard_cost' => $pData['standard_cost'] ?? $product->standard_cost,
                        'hsn_code' => $pData['hsn_code'] ?? $product->hsn_code,
                        'min_order_qty' => $pData['min_order_qty'] ?? $product->min_order_qty,
                        'packaging' => $pData['packaging'] ?? $product->packaging,
                    ]);
                    $updatedCount++;
                }
            }

            DB::commit();

            return redirect()->route('products.bulk-update.index')
                ->with('success', "✓ Updated {$updatedCount} products from grid editor successfully!");
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('Grid update failed: '.$e->getMessage());

            return back()->with('error', 'Grid update failed: '.$e->getMessage());
        }
    }
}
