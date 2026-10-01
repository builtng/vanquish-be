<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Session extends Model
{
    protected $table = 'consultation_sessions';

    protected $fillable = [
        'client_id',
        'tc_id',
        'session_type',
        'scheduled_at',
        'completed_at',
        'status',
        'duration_minutes',
        'notes',
        'payment_status',
        'payment_amount',
        'stripe_payment_intent_id',
        'paid_at',
        'payment_method',
        'is_block_booking',
        'block_number',
        'total_sessions_in_block',
        'booking_deadline',
        'booking_reminder_sent',
        'auto_deduction_applied',
    ];

    protected $casts = [
        'scheduled_at' => 'datetime',
        'completed_at' => 'datetime',
        'booking_deadline' => 'date',
        'is_block_booking' => 'boolean',
        'booking_reminder_sent' => 'boolean',
        'auto_deduction_applied' => 'boolean',
        'payment_amount' => 'decimal:2',
    ];

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function tc(): BelongsTo
    {
        return $this->belongsTo(TrainingCounsellor::class, 'tc_id');
    }
}
