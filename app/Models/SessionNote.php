<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class SessionNote extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'training_counsellor_id',
        'client_id',
        'type',
        'content',
        'status',
        'archived_at',
    ];

    protected $casts = [
        'content' => 'array',
        'archived_at' => 'datetime',
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
