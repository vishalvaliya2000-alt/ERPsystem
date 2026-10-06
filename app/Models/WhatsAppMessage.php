<?php

namespace App\Models;

use App\Models\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WhatsAppMessage extends Model
{
    use BelongsToTenant;

    protected $table = 'whatsapp_messages';

    protected $fillable = [
        'firm_id',
        'recipient_phone',
        'recipient_name',
        'message_type',
        'reference_type',
        'reference_id',
        'template_name',
        'message_body',
        'document_url',
        'meta_message_id',
        'status',
        'error_message',
    ];

    public function firm(): BelongsTo
    {
        return $this->belongsTo(Firm::class, 'firm_id');
    }
}
