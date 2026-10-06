<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\PartyPriceOverride;
use App\Models\Product;
use App\Models\ProductPriceSlab;

class PricingService
{
    /**
     * Resolve the effective selling rate and margin for a product and customer.
     *
     * Hierarchy:
     * 1. Active Party Price Override for this customer + product.
     * 2. Quantity / Tier Slab pricing for this product matching tier & quantity threshold.
     * 3. Product Standard Base Rate.
     *
     * @return array{
     *     rate: float,
     *     source: string,
     *     cost: float,
     *     unit_margin: float,
     *     margin_percentage: float,
     *     hsn_code: string,
     *     notes: string
     * }
     */
    public static function resolveRate(
        Customer $customer,
        Product $product,
        float $quantity = 1.0,
        ?string $tier = null
    ): array {
        $cost = (float) ($product->standard_cost ?? 0.0);
        $hsnCode = $product->hsn_code ?: '07129090';

        // 1. Check Party-Specific Rate Override
        $override = PartyPriceOverride::where('customer_id', $customer->id)
            ->where('product_id', $product->id)
            ->first();

        if ($override && $override->isValid()) {
            $rate = (float) $override->custom_rate;
            $unitMargin = $rate - $cost;
            $marginPct = $rate > 0 ? round(($unitMargin / $rate) * 100, 2) : 0.0;

            return [
                'rate' => $rate,
                'source' => 'PARTY_OVERRIDE',
                'cost' => $cost,
                'unit_margin' => $unitMargin,
                'margin_percentage' => $marginPct,
                'hsn_code' => $override->custom_hsn ?: $hsnCode,
                'notes' => "Party-specific agreed rate for {$customer->company_name}",
            ];
        }

        // 2. Check Price Slab / Tier
        $slabQuery = ProductPriceSlab::where('product_id', $product->id)
            ->where('min_quantity', '<=', $quantity);

        if (! empty($tier)) {
            $slabQuery->where('tier_name', $tier);
        }

        $slab = $slabQuery->orderByDesc('min_quantity')->first();

        if ($slab) {
            $rate = (float) $slab->rate;
            $unitMargin = $rate - $cost;
            $marginPct = $rate > 0 ? round(($unitMargin / $rate) * 100, 2) : 0.0;

            return [
                'rate' => $rate,
                'source' => 'PRICE_TIER',
                'cost' => $cost,
                'unit_margin' => $unitMargin,
                'margin_percentage' => $marginPct,
                'hsn_code' => $hsnCode,
                'notes' => "Volume tier slab ({$slab->tier_name} >= {$slab->min_quantity} {$product->uom})",
            ];
        }

        // 3. Base Standard Rate Fallback
        $rate = (float) ($product->standard_rate ?? 0.0);
        $unitMargin = $rate - $cost;
        $marginPct = $rate > 0 ? round(($unitMargin / $rate) * 100, 2) : 0.0;

        return [
            'rate' => $rate,
            'source' => 'STANDARD_RATE',
            'cost' => $cost,
            'unit_margin' => $unitMargin,
            'margin_percentage' => $marginPct,
            'hsn_code' => $hsnCode,
            'notes' => 'Product standard catalog price',
        ];
    }
}
