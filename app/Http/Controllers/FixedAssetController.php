<?php

namespace App\Http\Controllers;

use App\Models\FixedAsset;
use App\Services\AccountingService;
use App\Services\CompanyFirmContext;
use App\Services\DepreciationService;
use Carbon\Carbon;
use Illuminate\Http\Request;

class FixedAssetController extends Controller
{
    public function __construct(
        protected DepreciationService $depreciationService,
        protected AccountingService $accountingService
    ) {}

    /**
     * Fixed Assets Register.
     */
    public function index(Request $request)
    {
        $firmId = CompanyFirmContext::getCurrentFirmId();
        $category = $request->query('category');
        $status = $request->query('status', 'ACTIVE');

        $query = FixedAsset::with(['firm', 'depreciations'])
            ->orderBy('purchase_date', 'desc')
            ->orderBy('id', 'desc');

        if ($firmId) {
            $query->where('firm_id', $firmId);
        }

        if ($category) {
            $query->where('asset_category', strtoupper($category));
        }

        if ($status && $status !== 'ALL') {
            $query->where('status', strtoupper($status));
        }

        $assets = $query->paginate(20)->withQueryString();

        // Register summary totals
        $metricsQuery = FixedAsset::query();
        if ($firmId) {
            $metricsQuery->where('firm_id', $firmId);
        }

        $totalOriginalCost = (float) (clone $metricsQuery)->sum('purchase_cost');
        $totalDepreciation = (float) (clone $metricsQuery)->sum('accumulated_depreciation');
        $totalNetBookValue = (float) (clone $metricsQuery)->sum('current_book_value');
        $activeAssetCount = (int) (clone $metricsQuery)->where('status', 'ACTIVE')->count();

        return view('fixed-assets.index', compact(
            'assets',
            'totalOriginalCost',
            'totalDepreciation',
            'totalNetBookValue',
            'activeAssetCount',
            'category',
            'status'
        ));
    }

    /**
     * Registers a new fixed asset.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'asset_code' => 'required|string|max:50|unique:fixed_assets,asset_code',
            'asset_name' => 'required|string|max:150',
            'asset_category' => 'required|string|in:PLANT_MACHINERY,VEHICLES,COMPUTERS,FURNITURE,OFFICE_EQUIPMENT',
            'purchase_date' => 'required|date',
            'purchase_cost' => 'required|numeric|min:0.01',
            'salvage_value' => 'nullable|numeric|min:0',
            'useful_life_years' => 'required|integer|min:1|max:50',
            'depreciation_method' => 'required|string|in:SLM,WDV',
            'depreciation_rate' => 'nullable|numeric|min:0|max:100',
        ]);

        $cost = round((float) $validated['purchase_cost'], 2);
        $salvage = round((float) ($validated['salvage_value'] ?? 0.0), 2);
        $usefulLife = (int) $validated['useful_life_years'];
        $rate = (float) ($validated['depreciation_rate'] ?? 0.0);

        if ($rate <= 0.0) {
            $rate = $validated['depreciation_method'] === 'SLM'
                ? round((100 / $usefulLife), 2)
                : round((100 / $usefulLife) * 1.5, 2);
        }

        $asset = FixedAsset::create([
            'firm_id' => CompanyFirmContext::getCurrentFirmId(),
            'asset_code' => $validated['asset_code'],
            'asset_name' => $validated['asset_name'],
            'asset_category' => $validated['asset_category'],
            'purchase_date' => Carbon::parse($validated['purchase_date']),
            'purchase_cost' => $cost,
            'salvage_value' => $salvage,
            'useful_life_years' => $usefulLife,
            'depreciation_method' => $validated['depreciation_method'],
            'depreciation_rate' => $rate,
            'accumulated_depreciation' => 0.00,
            'current_book_value' => $cost,
            'status' => 'ACTIVE',
        ]);

        return redirect()->route('fixed-assets.index')->with('success', "✓ Fixed Asset {$asset->asset_name} ({$asset->asset_code}) registered successfully.");
    }

    /**
     * Executes single asset depreciation.
     */
    public function runDepreciation(Request $request, int $id)
    {
        $asset = FixedAsset::findOrFail($id);

        $validated = $request->validate([
            'depreciation_amount' => 'nullable|numeric|min:0.01',
            'depreciation_date' => 'nullable|date',
        ]);

        $amount = ! empty($validated['depreciation_amount']) ? (float) $validated['depreciation_amount'] : null;
        $date = ! empty($validated['depreciation_date']) ? Carbon::parse($validated['depreciation_date']) : now();

        $depRecord = $this->depreciationService->postDepreciationVoucher($asset, $amount, $date);

        if (! $depRecord) {
            return back()->with('error', "Asset {$asset->asset_code} has already reached its salvage value (₹".number_format((float) $asset->salvage_value, 2).') or cannot be depreciated.');
        }

        return redirect()->route('fixed-assets.show', $asset->id)
            ->with('success', '✓ Depreciation of ₹'.number_format((float) $depRecord->depreciation_amount, 2)." charged on {$asset->asset_name}. Book value updated to ₹".number_format((float) $depRecord->book_value_after, 2).'.');
    }

    /**
     * Batch runs depreciation across all active assets.
     */
    public function batchDepreciation(Request $request)
    {
        $period = $request->input('period', 'YEAR');
        $firmId = CompanyFirmContext::getCurrentFirmId();

        $result = $this->depreciationService->runBatchDepreciation($firmId, now(), $period);

        return redirect()->route('fixed-assets.index')
            ->with('success', "✓ Batch depreciation completed: {$result['processed_count']} assets processed. Total depreciation: ₹".number_format($result['total_depreciation'], 2).'.');
    }

    /**
     * Shows fixed asset details and depreciation schedule.
     */
    public function show(int $id)
    {
        $asset = FixedAsset::with(['depreciations.journalVoucher', 'firm'])->findOrFail($id);

        $depreciations = $asset->depreciations()->orderBy('depreciation_date', 'desc')->get();
        $calculatedAnnual = $this->depreciationService->calculateDepreciation($asset, now(), 'YEAR');
        $calculatedMonthly = $this->depreciationService->calculateDepreciation($asset, now(), 'MONTH');

        return view('fixed-assets.show', compact('asset', 'depreciations', 'calculatedAnnual', 'calculatedMonthly'));
    }

    /**
     * Disposes or writes off an asset.
     */
    public function dispose(Request $request, int $id)
    {
        $asset = FixedAsset::findOrFail($id);

        $validated = $request->validate([
            'disposal_date' => 'required|date',
            'sale_proceeds' => 'required|numeric|min:0',
            'reason' => 'nullable|string|max:255',
        ]);

        $proceeds = round((float) $validated['sale_proceeds'], 2);
        $bookValue = (float) $asset->current_book_value;
        $gainOrLoss = round($proceeds - $bookValue, 2);

        $asset->update([
            'status' => 'DISPOSED',
            'current_book_value' => 0.00,
        ]);

        $gainLossMsg = $gainOrLoss >= 0
            ? 'Gain on sale: ₹'.number_format($gainOrLoss, 2)
            : 'Loss on sale: ₹'.number_format(abs($gainLossMsg ?? $gainOrLoss), 2);

        return redirect()->route('fixed-assets.index')
            ->with('success', "✓ Asset {$asset->asset_code} disposed. Proceeds: ₹".number_format($proceeds, 2).". ({$gainLossMsg})");
    }
}
