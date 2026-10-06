<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

class TenantUsage extends Model
{
    use CentralConnection;

    protected $table = 'tenant_usage';

    protected $fillable = [
        'tenant_id',
        'metric_key',
        'period',
        'count',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public static function incrementUsage(string $tenantId, string $metricKey, int $by = 1): int
    {
        $period = date('Y-m');
        $record = static::firstOrCreate([
            'tenant_id' => $tenantId,
            'metric_key' => $metricKey,
            'period' => $period,
        ], ['count' => 0]);

        $record->increment('count', $by);

        return $record->count;
    }

    public static function incrementMetric(string $metricKey, int $by = 1): int
    {
        $tenantId = tenancy()->initialized ? (string) tenant('id') : 'central';

        return static::incrementUsage($tenantId, $metricKey, $by);
    }

    public static function getUsage(string $tenantId, string $metricKey): int
    {
        $period = date('Y-m');

        return static::where('tenant_id', $tenantId)
            ->where('metric_key', $metricKey)
            ->where('period', $period)
            ->value('count') ?? 0;
    }
}
