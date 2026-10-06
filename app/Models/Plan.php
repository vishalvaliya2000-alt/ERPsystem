<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

class Plan extends Model
{
    use CentralConnection;

    protected $table = 'plans';

    protected $fillable = [
        'name',
        'slug',
        'price_monthly',
        'price_yearly',
        'currency',
        'is_active',
    ];

    protected $casts = [
        'price_monthly' => 'decimal:2',
        'price_yearly' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    public function features(): HasMany
    {
        return $this->hasMany(PlanFeature::class);
    }

    public function getFeature(string $key): ?PlanFeature
    {
        return $this->features->firstWhere('feature_key', $key);
    }
}
