<?php

namespace App\Models;

use App\Models\Traits\BelongsToTenant;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class ProductDocument extends Model
{
    use BelongsToTenant;

    public const TYPE_PHOTO = 'PHOTO';

    public const TYPE_COA = 'COA';

    public const TYPE_SPECIFICATION = 'SPECIFICATION';

    public const TYPE_MSDS = 'MSDS';

    public const TYPE_OTHER = 'OTHER';

    public static array $types = [
        self::TYPE_PHOTO => 'Product Photo',
        self::TYPE_COA => 'Certificate of Analysis (COA)',
        self::TYPE_SPECIFICATION => 'Specification Sheet / TDS',
        self::TYPE_MSDS => 'MSDS / SDS',
        self::TYPE_OTHER => 'Other Document',
    ];

    protected $fillable = [
        'tenant_id',
        'product_id',
        'document_type',
        'title',
        'file_path',
        'file_name',
        'file_size',
        'mime_type',
        'version',
        'valid_until',
        'is_latest',
        'is_primary',
        'sort_order',
        'notes',
        'created_by_user_id',
    ];

    protected $casts = [
        'valid_until' => 'date',
        'is_latest' => 'boolean',
        'is_primary' => 'boolean',
        'file_size' => 'integer',
        'sort_order' => 'integer',
    ];

    protected $appends = [
        'formatted_file_size',
        'is_image',
        'is_pdf',
        'is_expired',
        'type_label',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    public function createdByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function scopePhotos(Builder $query): Builder
    {
        return $query->where('document_type', self::TYPE_PHOTO)->orderBy('sort_order')->orderBy('id');
    }

    public function scopeDocuments(Builder $query): Builder
    {
        return $query->where('document_type', '!=', self::TYPE_PHOTO);
    }

    public function scopeLatestOnly(Builder $query): Builder
    {
        return $query->where('is_latest', true);
    }

    public function getFormattedFileSizeAttribute(): string
    {
        $bytes = (int) $this->file_size;
        if ($bytes >= 1048576) {
            return number_format($bytes / 1048576, 2).' MB';
        }
        if ($bytes >= 1024) {
            return number_format($bytes / 1024, 1).' KB';
        }

        return $bytes.' B';
    }

    public function getIsImageAttribute(): bool
    {
        if (str_starts_with($this->mime_type ?? '', 'image/')) {
            return true;
        }

        return (bool) preg_match('/\.(jpg|jpeg|png|webp|gif|bmp)$/i', $this->file_name ?? '');
    }

    public function getIsPdfAttribute(): bool
    {
        return $this->mime_type === 'application/pdf' || str_ends_with(strtolower($this->file_name ?? ''), '.pdf');
    }

    public function getIsExpiredAttribute(): bool
    {
        if (! $this->valid_until) {
            return false;
        }

        return $this->valid_until->isPast();
    }

    public function getDaysUntilExpiryAttribute(): ?int
    {
        if (! $this->valid_until) {
            return null;
        }

        return (int) Carbon::today()->diffInDays($this->valid_until, false);
    }

    public function getTypeLabelAttribute(): string
    {
        return match ($this->document_type) {
            self::TYPE_PHOTO => 'Product Photo',
            self::TYPE_COA => 'Certificate of Analysis (COA)',
            self::TYPE_SPECIFICATION => 'Specification Sheet / TDS',
            self::TYPE_MSDS => 'MSDS / SDS',
            default => 'Other Document',
        };
    }

    public function getTypeIconAttribute(): string
    {
        return match ($this->document_type) {
            self::TYPE_PHOTO => '🖼️',
            self::TYPE_COA => '📜',
            self::TYPE_SPECIFICATION => '📋',
            self::TYPE_MSDS => '🛡️',
            default => '📁',
        };
    }

    public function getTypeBadgeClassAttribute(): string
    {
        return match ($this->document_type) {
            self::TYPE_PHOTO => 'bg-purple-100 text-purple-800 border-purple-200',
            self::TYPE_COA => 'bg-emerald-100 text-emerald-800 border-emerald-200',
            self::TYPE_SPECIFICATION => 'bg-blue-100 text-blue-800 border-blue-200',
            self::TYPE_MSDS => 'bg-amber-100 text-amber-800 border-amber-200',
            default => 'bg-slate-100 text-slate-800 border-slate-200',
        };
    }

    public function getStorageDisk(): string
    {
        return config('filesystems.default') === 's3' ? 's3' : 'public';
    }

    public function getFileUrlAttribute(): string
    {
        $disk = $this->getStorageDisk();

        return Storage::disk($disk)->url($this->file_path);
    }

    public function getDownloadUrlAttribute(): string
    {
        return route('products.documents.download', ['productId' => $this->product_id, 'docId' => $this->id]);
    }

    public function getPreviewUrlAttribute(): string
    {
        return route('products.documents.preview', ['productId' => $this->product_id, 'docId' => $this->id]);
    }
}
