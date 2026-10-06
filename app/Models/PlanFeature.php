<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

class PlanFeature extends Model
{
    use CentralConnection;

    protected $table = 'plan_features';

    protected $fillable = [
        'plan_id',
        'feature_key',
        'limit_type',
        'value',
        'description',
    ];

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }
}
