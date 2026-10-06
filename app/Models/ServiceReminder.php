<?php

namespace App\Models;

use App\Models\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ServiceReminder extends Model
{
    use BelongsToTenant;

    protected $table = 'service_reminders';

    protected $fillable = [
        'firm_id',
        'fixed_asset_id',
        'service_name',
        'service_type',
        'frequency_days',
        'last_serviced_date',
        'next_due_date',
        'assigned_to_user_id',
        'status',
        'priority',
        'estimated_cost',
        'notes',
    ];

    protected $casts = [
        'last_serviced_date' => 'date',
        'next_due_date' => 'date',
        'estimated_cost' => 'decimal:2',
        'frequency_days' => 'integer',
    ];

    public function firm(): BelongsTo
    {
        return $this->belongsTo(Firm::class, 'firm_id');
    }

    public function fixedAsset(): BelongsTo
    {
        return $this->belongsTo(FixedAsset::class, 'fixed_asset_id');
    }

    public function assignedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to_user_id');
    }

    public function isOverdue(): bool
    {
        return $this->next_due_date && $this->next_due_date->isPast() && $this->status !== 'COMPLETED';
    }

    public function markCompleted(?string $notes = null): void
    {
        $now = now();
        $isRecurring = ($this->frequency_days > 0);
        $this->update([
            'status' => $isRecurring ? 'UPCOMING' : 'COMPLETED',
            'last_serviced_date' => $now,
            'next_due_date' => $isRecurring ? $now->copy()->addDays($this->frequency_days) : $this->next_due_date,
            'notes' => $notes ?: $this->notes,
        ]);
    }

    public function scopeDue(Builder $query): Builder
    {
        return $query->where('status', '!=', 'COMPLETED')
            ->whereDate('next_due_date', '<=', now());
    }

    public function scopeUpcoming(Builder $query, int $days = 7): Builder
    {
        return $query->where('status', '!=', 'COMPLETED')
            ->whereDate('next_due_date', '>', now())
            ->whereDate('next_due_date', '<=', now()->addDays($days));
    }
}
