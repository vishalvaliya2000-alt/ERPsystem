<?php

namespace Database\Seeders;

use App\Models\InventoryStock;
use App\Models\Lead;
use App\Models\Product;
use App\Models\ProductionBatch;
use App\Models\Quotation;
use App\Models\QuotationItem;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class ProductionAndQuotationSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Seed Inventory Stocks for all products
        foreach (Product::all() as $prod) {
            $isGarlic = $prod->category?->slug === 'garlic';
            $stockQty = $isGarlic ? rand(8000, 25000) : rand(12000, 35000);

            InventoryStock::updateOrCreate(
                ['product_id' => $prod->id],
                [
                    'current_stock_qty' => $stockQty,
                    'min_threshold_qty' => 5000,
                    'godown_location' => 'Bhavnagar Plant Godown A',
                    'last_audited_at' => Carbon::now()->subDays(3),
                ]
            );

            // Production Batch for key products
            if (in_array($prod->product_code, ['GP001', 'WOP001', 'POP001', 'GF001'])) {
                ProductionBatch::updateOrCreate(
                    ['batch_number' => "B-{$prod->product_code}-".date('my')],
                    [
                        'product_id' => $prod->id,
                        'production_date' => Carbon::now()->subDays(5),
                        'batch_qty' => 10000,
                        'available_qty' => 7500,
                        'moisture_percentage' => 4.8,
                        'sensory_grade' => 'Export Grade A+',
                        'raw_lot_number' => 'LOT-BHAV-'.rand(100, 999),
                        'status' => 'QUALITY_APPROVED',
                        'notes' => 'Passed 100-mesh sieve and microbiological evaluation.',
                    ]
                );
            }
        }

        // 2. Demonstration Quotations
        $lead = Lead::where('company_name', 'like', '%Gimi Michi%')->first();
        $pinkOnion = Product::where('product_code', 'POP001')->first();
        $garlicFlakes = Product::where('product_code', 'GF001')->first();

        if ($lead && $pinkOnion) {
            $quote = Quotation::updateOrCreate(
                ['quotation_number' => 'QUO-2026-042'],
                [
                    'lead_id' => $lead->id,
                    'recipient_name' => $lead->contact_person,
                    'recipient_company' => $lead->company_name,
                    'recipient_phone' => $lead->phone,
                    'recipient_email' => $lead->email,
                    'quotation_date' => Carbon::now()->subDays(2),
                    'valid_until' => Carbon::now()->addDays(12),
                    'subtotal' => 850000,
                    'tax_rate' => 5.00,
                    'tax_amount' => 42500,
                    'total_amount' => 892500,
                    'payment_terms' => '30% Advance, 70% against LR copy',
                    'freight_terms' => 'FOR Ahmedabad (Door Delivery via ACPL)',
                    'delivery_timeline' => 'Immediate dispatch within 3 days of PO',
                    'status' => 'SENT',
                    'notes' => 'Quotation prepared for 5,000 kg Pink Onion Powder and 2,000 kg Garlic Flakes.',
                ]
            );

            QuotationItem::updateOrCreate(
                ['quotation_id' => $quote->id, 'product_id' => $pinkOnion->id],
                [
                    'quantity' => 5000,
                    'uom' => 'KG',
                    'rate' => 115.00,
                    'amount' => 575000,
                    'packaging' => '25 KG Inner Poly / Outer HDPE Bag',
                    'specifications' => 'A-Grade Pink Onion Powder, max 5% moisture, Salmonella Negative',
                ]
            );

            if ($garlicFlakes) {
                QuotationItem::updateOrCreate(
                    ['quotation_id' => $quote->id, 'product_id' => $garlicFlakes->id],
                    [
                        'quantity' => 2000,
                        'uom' => 'KG',
                        'rate' => 137.50,
                        'amount' => 275000,
                        'packaging' => '20 KG Carton Box with poly liner',
                        'specifications' => 'Premium White Garlic Flakes, clean sorted, zero skin impurities',
                    ]
                );
            }
        }
    }
}
