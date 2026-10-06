<?php

namespace App\Models;

use App\Models\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductionBatch extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'batch_number',
        'product_id',
        'production_date',
        'batch_qty',
        'available_qty',
        'moisture_percentage',
        'sensory_grade',
        'raw_lot_number',
        'status',
        'notes',
    ];

    protected $casts = [
        'production_date' => 'datetime',
        'batch_qty' => 'decimal:2',
        'available_qty' => 'decimal:2',
        'moisture_percentage' => 'decimal:2',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }
}
