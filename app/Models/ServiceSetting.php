<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ServiceSetting extends Model
{
    protected $fillable = [
        'service_name',
        'capacity_full',
        'capacity_message',
        'alternative_url',
        'consultation_price',
        'session_price',
        'block_price',
        'booking_enabled',
    ];

    protected $casts = [
        'capacity_full'    => 'boolean',
        'booking_enabled'  => 'boolean',
    ];
}
