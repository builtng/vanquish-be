<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SessionNote extends Model
{
    protected $fillable = [
        'training_counsellor_id',
        'client_id',
        'type',
        'content',
        'status'
    ];

    protected $casts = [
        'content' => 'array',
    ];

    public function counsellor(): BelongsTo
    {
        return $this->belongsTo(TrainingCounsellor::class, 'training_counsellor_id');
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class, 'client_id');
    }
}
