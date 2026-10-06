<?php

namespace App\Services;

use App\Models\Company;
use App\Models\Firm;
use Illuminate\Database\Eloquent\Collection;

class CompanyFirmContext
{
    protected static ?Company $cachedCompany = null;

    protected static ?Firm $cachedFirm = null;

    /**
     * Get the currently active Firm from session or default fallback.
     */
    public static function getFirm(): ?Firm
    {
        if (static::$cachedFirm !== null) {
            return static::$cachedFirm;
        }

        try {
            $firmId = session('active_firm_id');
            if ($firmId) {
                $firm = Firm::with('company')->where('is_active', true)->find($firmId);
                if ($firm) {
                    static::$cachedFirm = $firm;
                    static::$cachedCompany = $firm->company;

                    return $firm;
                }
            }

            // Fallback to default active firm or first active firm
            $defaultFirm = Firm::with('company')
                ->where('is_active', true)
                ->orderByDesc('is_default')
                ->first();

            if ($defaultFirm) {
                session([
                    'active_firm_id' => $defaultFirm->id,
                    'active_company_id' => $defaultFirm->company_id,
                ]);
                static::$cachedFirm = $defaultFirm;
                static::$cachedCompany = $defaultFirm->company;

                return $defaultFirm;
            }
        } catch (\Throwable $e) {
            // Ignore if tables do not exist yet (e.g. before migration)
        }

        return null;
    }

    /**
     * Alias for getFirm()
     */
    public static function getCurrentFirm(): ?Firm
    {
        return static::getFirm();
    }

    /**
     * Get the currently active Company.
     */
    public static function getCompany(): ?Company
    {
        if (static::$cachedCompany !== null) {
            return static::$cachedCompany;
        }

        $firm = static::getFirm();
        if ($firm && $firm->company) {
            static::$cachedCompany = $firm->company;

            return static::$cachedCompany;
        }

        try {
            $companyId = session('active_company_id');
            if ($companyId) {
                $company = Company::where('is_active', true)->find($companyId);
                if ($company) {
                    static::$cachedCompany = $company;

                    return $company;
                }
            }

            $defaultCompany = Company::where('is_active', true)
                ->orderByDesc('is_default')
                ->first();

            if ($defaultCompany) {
                session(['active_company_id' => $defaultCompany->id]);
                static::$cachedCompany = $defaultCompany;

                return $defaultCompany;
            }
        } catch (\Throwable $e) {
            // Ignore
        }

        return null;
    }

    /**
     * Alias for getCompany()
     */
    public static function getCurrentCompany(): ?Company
    {
        return static::getCompany();
    }

    /**
     * Get ID of the currently active firm.
     */
    public static function getCurrentFirmId(): ?int
    {
        return static::getFirm()?->id;
    }

    /**
     * Get ID of the currently active company.
     */
    public static function getCurrentCompanyId(): ?int
    {
        return static::getCompany()?->id;
    }

    /**
     * Set active firm (and its parent company).
     */
    public static function setFirm(int|Firm $firm): void
    {
        $firmModel = is_numeric($firm) ? Firm::with('company')->findOrFail($firm) : $firm;

        session([
            'active_firm_id' => $firmModel->id,
            'active_company_id' => $firmModel->company_id,
        ]);

        static::$cachedFirm = $firmModel;
        static::$cachedCompany = $firmModel->company;
    }

    /**
     * Set active company and select its primary firm.
     */
    public static function setCompany(int|Company $company): void
    {
        $companyModel = is_numeric($company) ? Company::with('firms')->findOrFail($company) : $company;

        session(['active_company_id' => $companyModel->id]);
        static::$cachedCompany = $companyModel;

        $targetFirm = $companyModel->firms()
            ->where('is_active', true)
            ->orderByDesc('is_default')
            ->first();

        if ($targetFirm) {
            session(['active_firm_id' => $targetFirm->id]);
            static::$cachedFirm = $targetFirm;
        } else {
            session()->forget('active_firm_id');
            static::$cachedFirm = null;
        }
    }

    /**
     * Get all active companies with their active firms.
     *
     * @return Collection<int, Company>
     */
    public static function availableCompanies(): Collection
    {
        try {
            return Company::with(['firms' => function ($q) {
                $q->where('is_active', true)->orderByDesc('is_default')->orderBy('firm_name');
            }])
                ->where('is_active', true)
                ->orderByDesc('is_default')
                ->orderBy('legal_name')
                ->get();
        } catch (\Throwable $e) {
            return new Collection;
        }
    }

    /**
     * Clear cached in-memory context (useful between tests or switching).
     */
    public static function flushCache(): void
    {
        static::$cachedCompany = null;
        static::$cachedFirm = null;
    }
}
