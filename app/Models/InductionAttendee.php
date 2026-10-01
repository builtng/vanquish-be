<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;
use Carbon\Carbon;

class InductionAttendee extends Model
{
    protected $fillable = [
        'induction_id',
        'tc_id',
        'acceptance_token',
        'status',
        'accepted_at',
        'expires_at',
    ];

    protected $casts = [
        'accepted_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($attendee) {
            if (!$attendee->acceptance_token) {
                $attendee->acceptance_token = Str::random(64);
            }
            if (!$attendee->expires_at) {
                // 72 hours from now
                $attendee->expires_at = Carbon::now()->addHours(72);
            }
        });
    }

    public function induction(): BelongsTo
    {
        return $this->belongsTo(Induction::class);
    }

    public function trainingCounsellor(): BelongsTo
    {
        return $this->belongsTo(TrainingCounsellor::class, 'tc_id');
    }

    public function isExpired(): bool
    {
        return Carbon::now()->isAfter($this->expires_at);
    }

    public function canAccept(): bool
    {
        return $this->status === 'pending' && !$this->isExpired();
    }
}

