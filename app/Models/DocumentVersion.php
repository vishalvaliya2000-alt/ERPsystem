<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DocumentVersion extends Model
{
    protected $fillable = [
        'document_id',
        'version_number',
        'file_path',
        'file_name',
        'file_size',
        'mime_type',
        'uploaded_by_user_id',
        'change_note',
        'is_active',
    ];

    protected $casts = [
        'version_number' => 'integer',
        'file_size' => 'integer',
        'is_active' => 'boolean',
    ];

    public function document(): BelongsTo
    {
        return $this->belongsTo(BusinessDocument::class, 'document_id');
    }

    public function uploadedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by_user_id');
    }

    public function getFormattedFileSizeAttribute(): string
    {
        $bytes = $this->file_size;
        if ($bytes >= 1048576) {
            return number_format($bytes / 1048576, 2).' MB';
        }
        if ($bytes >= 1024) {
            return number_format($bytes / 1024, 1).' KB';
        }

        return $bytes.' B';
    }

    public function getIsPdfAttribute(): bool
    {
        return $this->mime_type === 'application/pdf' || str_ends_with(strtolower($this->file_name), '.pdf');
    }

    public function getIsImageAttribute(): bool
    {
        return str_starts_with($this->mime_type ?? '', 'image/') || preg_match('/\.(jpg|jpeg|png|webp|bmp)$/i', $this->file_name);
    }

    public function getIsExcelAttribute(): bool
    {
        return str_contains($this->mime_type ?? '', 'spreadsheet') || preg_match('/\.(xlsx|xls|csv)$/i', $this->file_name);
    }
}
