<?php

namespace App\Models;

use App\Models\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Hash;

class ProductDocumentShare extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'product_id',
        'share_token',
        'title',
        'recipient_email',
        'selected_document_ids',
        'password_hash',
        'expires_at',
        'views_count',
        'last_accessed_at',
        'created_by_user_id',
    ];

    protected $casts = [
        'selected_document_ids' => 'array',
        'expires_at' => 'datetime',
        'last_accessed_at' => 'datetime',
        'views_count' => 'integer',
    ];

    protected $appends = [
        'is_expired',
        'has_password',
        'share_url',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    public function createdByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function getIsExpiredAttribute(): bool
    {
        if (! $this->expires_at) {
            return false;
        }

        return $this->expires_at->isPast();
    }

    public function getHasPasswordAttribute(): bool
    {
        return ! empty($this->password_hash);
    }

    public function verifyPassword(?string $password): bool
    {
        if (empty($this->password_hash)) {
            return true;
        }
        if (empty($password)) {
            return false;
        }

        return Hash::check($password, $this->password_hash);
    }

    public function getShareUrlAttribute(): string
    {
        return route('products.documents.shared', ['shareToken' => $this->share_token]);
    }

    public function getDocuments(): Collection
    {
        $query = ProductDocument::where('product_id', $this->product_id);

        if (! empty($this->selected_document_ids) && is_array($this->selected_document_ids)) {
            $query->whereIn('id', $this->selected_document_ids);
        }

        return $query->orderBy('document_type')->orderBy('sort_order')->orderByDesc('is_latest')->get();
    }
}
