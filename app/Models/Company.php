<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

class Company extends Model
{
    use LogsActivity;

    protected $fillable = [
        'legal_name',
        'trade_name',
        'cin',
        'pan',
        'logo_path',
        'website',
        'is_default',
        'is_active',
    ];

    protected $casts = [
        'is_default' => 'boolean',
        'is_active' => 'boolean',
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('companies')
            ->logOnly(['legal_name', 'trade_name', 'cin', 'pan', 'website', 'is_default', 'is_active'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }

    public function firms(): HasMany
    {
        return $this->hasMany(Firm::class);
    }

    /**
     * Mark this company as the default company, unsetting other companies.
     */
    public function makeDefault(): void
    {
        static::where('id', '!=', $this->id)->update(['is_default' => false]);
        $this->update(['is_default' => true]);
    }
}
