<?php

namespace App\Services;

use App\Models\AssetDepreciation;
use App\Models\FixedAsset;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class DepreciationService
{
    public function __construct(
        protected AccountingService $accountingService
    ) {}

    /**
     * Calculates depreciation amount for an asset according to its configured method (SLM or WDV).
     */
    public function calculateDepreciation(FixedAsset $asset, ?Carbon $date = null, string $period = 'YEAR'): float
    {
        if (strtoupper($asset->status) !== 'ACTIVE') {
            return 0.0;
        }

        $currentBookValue = (float) $asset->current_book_value;
        $salvageValue = (float) $asset->salvage_value;
        $maxDepreciable = max(0.0, round($currentBookValue - $salvageValue, 2));

        if ($maxDepreciable <= 0.0) {
            return 0.0;
        }

        $method = strtoupper($asset->depreciation_method ?: 'SLM');
        $depreciation = 0.0;

        if ($method === 'SLM') {
            // Straight Line Method: (Cost - Salvage) / Useful Life
            $cost = (float) $asset->purchase_cost;
            $usefulLife = max(1, (int) $asset->useful_life_years);

            $annualAmount = round(($cost - $salvageValue) / $usefulLife, 2);
            $depreciation = strtoupper($period) === 'MONTH' ? round($annualAmount / 12, 2) : $annualAmount;
        } elseif ($method === 'WDV') {
            // Written Down Value: Current Book Value * Rate%
            $rate = (float) $asset->depreciation_rate;
            if ($rate <= 0.0) {
                $usefulLife = max(1, (int) $asset->useful_life_years);
                $rate = round((100 / $usefulLife) * 1.5, 2); // Standard WDV factor
            }

            $annualAmount = round($currentBookValue * ($rate / 100), 2);
            $depreciation = strtoupper($period) === 'MONTH' ? round($annualAmount / 12, 2) : $annualAmount;
        }

        return min($depreciation, $maxDepreciable);
    }

    /**
     * Posts a depreciation entry, updates the asset book value, and generates a DEPRECIATION journal voucher.
     * Dr. Depreciation Expense (5030)
     *   Cr. Fixed Assets Ledger (1050)
     */
    public function postDepreciationVoucher(FixedAsset $asset, ?float $amount = null, ?Carbon $date = null): ?AssetDepreciation
    {
        $depreciationDate = $date ?? now();
        $depreciationAmount = $amount !== null ? round($amount, 2) : $this->calculateDepreciation($asset, $depreciationDate);

        if ($depreciationAmount <= 0.0) {
            return null;
        }

        return DB::transaction(function () use ($asset, $depreciationAmount, $depreciationDate) {
            $bookValueBefore = (float) $asset->current_book_value;
            $salvageValue = (float) $asset->salvage_value;

            $bookValueAfter = max($salvageValue, round($bookValueBefore - $depreciationAmount, 2));
            $actualDepreciation = round($bookValueBefore - $bookValueAfter, 2);

            if ($actualDepreciation <= 0.0) {
                return null;
            }

            // 1. Resolve Accounts
            $depExpenseAccount = $this->accountingService->getOrCreateAccount(
                '5030',
                'Depreciation Expense',
                'EXPENSES',
                'INDIRECT_EXPENSES'
            );

            $fixedAssetAccount = $this->accountingService->getOrCreateAccount(
                '1050',
                'Fixed Assets (Plant & Machinery)',
                'ASSETS',
                'FIXED_ASSETS'
            );

            // 2. Generate Balanced Journal Voucher
            $items = [
                [
                    'account_id' => $depExpenseAccount->id,
                    'debit_amount' => $actualDepreciation,
                    'credit_amount' => 0.00,
                    'remarks' => "Depreciation on {$asset->asset_name} ({$asset->asset_code})",
                ],
                [
                    'account_id' => $fixedAssetAccount->id,
                    'debit_amount' => 0.00,
                    'credit_amount' => $actualDepreciation,
                    'remarks' => "Accumulated depreciation write-down on {$asset->asset_code}",
                ],
            ];

            $voucher = $this->accountingService->createJournalVoucher([
                'firm_id' => $asset->firm_id,
                'voucher_type' => 'DEPRECIATION',
                'reference_type' => 'ASSET',
                'reference_id' => $asset->id,
                'voucher_date' => $depreciationDate,
                'narration' => 'Depreciation of ₹'.number_format($actualDepreciation, 2)." charged on {$asset->asset_name} ({$asset->asset_code}) via {$asset->depreciation_method}",
            ], $items);

            // 3. Update Fixed Asset Register
            $asset->update([
                'accumulated_depreciation' => round((float) $asset->accumulated_depreciation + $actualDepreciation, 2),
                'current_book_value' => $bookValueAfter,
            ]);

            // 4. Record Asset Depreciation Schedule Entry
            return AssetDepreciation::create([
                'fixed_asset_id' => $asset->id,
                'depreciation_date' => $depreciationDate,
                'depreciation_amount' => $actualDepreciation,
                'book_value_before' => $bookValueBefore,
                'book_value_after' => $bookValueAfter,
                'journal_voucher_id' => $voucher->id,
            ]);
        });
    }

    /**
     * Executes batch depreciation across all active eligible assets for a given firm.
     *
     * @return array{processed_count: int, total_depreciation: float}
     */
    public function runBatchDepreciation(?int $firmId = null, ?Carbon $date = null, string $period = 'YEAR'): array
    {
        $query = FixedAsset::active();
        if ($firmId) {
            $query->where('firm_id', $firmId);
        }

        $assets = $query->whereColumn('current_book_value', '>', 'salvage_value')->get();

        $processed = 0;
        $totalDep = 0.0;

        foreach ($assets as $asset) {
            $amount = $this->calculateDepreciation($asset, $date, $period);
            if ($amount > 0.0) {
                $depRecord = $this->postDepreciationVoucher($asset, $amount, $date);
                if ($depRecord) {
                    $processed++;
                    $totalDep += (float) $depRecord->depreciation_amount;
                }
            }
        }

        return [
            'processed_count' => $processed,
            'total_depreciation' => round($totalDep, 2),
        ];
    }
}
