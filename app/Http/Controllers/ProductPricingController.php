<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Customer;
use App\Models\PartyPriceOverride;
use App\Models\Product;
use App\Models\ProductCustomField;
use App\Models\ProductCustomValue;
use App\Models\ProductPriceSlab;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class ProductPricingController extends Controller
{
    /**
     * Store or update a quantity/volume price tier slab for a product.
     */
    public function storeSlab(Request $request, Product $product)
    {
        $validated = $request->validate([
            'tier_name' => 'required|string|max:100',
            'min_quantity' => 'required|numeric|min:0',
            'rate' => 'required|numeric|min:0.01',
        ]);

        $slab = ProductPriceSlab::updateOrCreate(
            [
                'product_id' => $product->id,
                'tier_name' => $validated['tier_name'],
                'min_quantity' => $validated['min_quantity'],
            ],
            [
                'rate' => $validated['rate'],
            ]
        );

        ActivityLog::create([
            'tenant_id' => $product->tenant_id,
            'activity_type' => 'NOTE',
            'title' => "Price Slab Updated ({$product->product_code})",
            'description' => "Set {$validated['tier_name']} slab for {$product->product_name}: >= {$validated['min_quantity']} {$product->uom} at ".formatINR($validated['rate']).'/'.$product->uom.'.',
        ]);

        return back()->with('success', "✓ Price slab '{$validated['tier_name']}' saved successfully for {$product->product_code}.");
    }

    /**
     * Delete a quantity/tier price slab.
     */
    public function destroySlab(Product $product, ProductPriceSlab $slab)
    {
        if ($slab->product_id !== $product->id) {
            abort(403);
        }

        $slabName = $slab->tier_name;
        $slab->delete();

        return back()->with('success', "✓ Deleted price tier '{$slabName}' for {$product->product_code}.");
    }

    /**
     * Store or update an agreed party-specific price override for a customer.
     */
    public function storePartyOverride(Request $request, Customer $customer)
    {
        $validated = $request->validate([
            'product_id' => 'required|exists:products,id',
            'custom_rate' => 'required|numeric|min:0.01',
            'custom_hsn' => 'nullable|string|max:20',
            'valid_until' => 'nullable|date',
        ]);

        $override = PartyPriceOverride::updateOrCreate(
            [
                'customer_id' => $customer->id,
                'product_id' => $validated['product_id'],
            ],
            [
                'custom_rate' => $validated['custom_rate'],
                'custom_hsn' => $validated['custom_hsn'] ?? null,
                'valid_until' => $validated['valid_until'] ?? null,
            ]
        );

        $product = Product::find($validated['product_id']);

        return back()->with('success', '✓ Special party rate of '.formatINR($validated['custom_rate'])." set for {$customer->company_name} on {$product->product_name}.");
    }

    /**
     * Delete an agreed party price override.
     */
    public function destroyPartyOverride(Customer $customer, PartyPriceOverride $override)
    {
        if ($override->customer_id !== $customer->id) {
            abort(403);
        }

        $override->delete();

        return back()->with('success', "✓ Removed party price override for {$customer->company_name}.");
    }

    /**
     * Store a new custom field definition for items.
     */
    public function storeCustomField(Request $request)
    {
        $validated = $request->validate([
            'field_name' => 'required|string|max:100',
            'field_type' => 'required|string|in:TEXT,NUMBER,DATE,DROPDOWN',
            'options' => 'nullable|string',
            'is_required' => 'nullable|boolean',
        ]);

        $fieldKey = Str::slug($validated['field_name'], '_');
        $optionsArray = null;

        if ($validated['field_type'] === 'DROPDOWN' && ! empty($validated['options'])) {
            $optionsArray = array_values(array_filter(array_map('trim', explode(',', $validated['options']))));
        }

        $field = ProductCustomField::updateOrCreate(
            ['field_key' => $fieldKey],
            [
                'field_name' => $validated['field_name'],
                'field_type' => $validated['field_type'],
                'options' => $optionsArray,
                'is_required' => $request->boolean('is_required'),
            ]
        );

        return back()->with('success', "✓ Custom field '{$field->field_name}' created successfully!");
    }

    /**
     * Delete a custom field definition and its values.
     */
    public function destroyCustomField(ProductCustomField $field)
    {
        $name = $field->field_name;
        $field->delete();

        return back()->with('success', "✓ Custom field '{$name}' deleted.");
    }

    /**
     * Save custom field values for a specific product.
     */
    public function updateCustomValues(Request $request, Product $product)
    {
        $values = $request->input('custom_fields', []);

        DB::beginTransaction();
        try {
            foreach ($values as $fieldId => $val) {
                if ($val !== null && $val !== '') {
                    ProductCustomValue::updateOrCreate(
                        [
                            'product_id' => $product->id,
                            'custom_field_id' => (int) $fieldId,
                        ],
                        [
                            'field_value' => is_array($val) ? json_encode($val) : (string) $val,
                        ]
                    );
                } else {
                    ProductCustomValue::where('product_id', $product->id)
                        ->where('custom_field_id', (int) $fieldId)
                        ->delete();
                }
            }

            DB::commit();

            return back()->with('success', "✓ Custom attributes updated for {$product->product_name}!");
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('Failed to update custom field values: '.$e->getMessage());

            return back()->with('error', 'Failed to update custom attributes: '.$e->getMessage());
        }
    }
}
