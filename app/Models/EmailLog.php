<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EmailLog extends Model
{
    protected $fillable = [
        'client_id',
        'email',
        'template_name',
        'payload',
        'status',
        'error_message',
        'sent_at'
    ];

    protected $casts = [
        'sent_at' => 'datetime',
        'payload' => 'array',
    ];


    public function client()
    {
        return $this->belongsTo(Client::class);
    }
}
