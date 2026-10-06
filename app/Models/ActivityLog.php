<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class ActivityLog extends Model
{
    protected $fillable = [
        'user_id',
        'action',
        'model_type',
        'model_id',
        'description',
        'changes',
        'ip_address',
        'user_agent',
        'hidden_at',
        'hidden_by',
    ];

    protected $casts = [
        'changes' => 'array',
        'hidden_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function hiddenByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'hidden_by');
    }

    public function model(): MorphTo
    {
        return $this->morphTo();
    }
}
