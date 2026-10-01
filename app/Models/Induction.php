<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Induction extends Model
{
    protected $fillable = [
        'uuid',
        'tc_id',
        'scheduled_at',
        'scheduled_end_at',
        'location',
        'notes',
        'status',
    ];

    protected $casts = [
        'scheduled_at' => 'datetime',
        'scheduled_end_at' => 'datetime',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($induction) {
            if (!$induction->uuid) {
                $induction->uuid = Str::uuid()->toString();
            }
        });
    }

    public function trainingCounsellor(): BelongsTo
    {
        return $this->belongsTo(TrainingCounsellor::class, 'tc_id');
    }

    public function attendees(): HasMany
    {
        return $this->hasMany(InductionAttendee::class);
    }

    public function confirmedAttendees(): HasMany
    {
        return $this->hasMany(InductionAttendee::class)->where('status', 'accepted');
    }

    public function pendingAttendees(): HasMany
    {
        return $this->hasMany(InductionAttendee::class)->where('status', 'pending');
    }
}

