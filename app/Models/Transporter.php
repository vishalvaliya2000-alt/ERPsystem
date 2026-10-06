<?php

namespace App\Models;

use App\Models\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class Transporter extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'transporter_name',
        'transporter_id_gst',
        'contact_person',
        'phone',
        'city',
        'state',
        'branch_address',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];
}
