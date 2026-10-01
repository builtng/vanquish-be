<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ConsultationBooking extends Model
{
    protected $fillable = [
        'booking_id',
        'client_id',
        'consultation_id',
        'consultation_slot_id',
        'client_name',
        'client_email',
        'client_phone',
        'scheduled_at',
        'status',
        'payment_status',
        'payment_amount',
        'stripe_payment_intent_id',
        'paid_at',
        'payment_method',
        'zoom_link',
        'meeting_id',
        'passcode',
        'reschedule_notes',
        'notes',
    ];

    protected $casts = [
        'scheduled_at' => 'datetime',
        'paid_at' => 'datetime',
        'payment_amount' => 'decimal:2',
    ];

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function consultation(): BelongsTo
    {
        return $this->belongsTo(Consultation::class);
    }

    public function consultationSlot(): BelongsTo
    {
        return $this->belongsTo(ConsultationSlot::class);
    }

    public static function nextBookingId(?\DateTimeInterface $date = null): string
    {
        $date = $date ?: now();
        $prefix = 'CONS-' . $date->format('Ymd') . '-';
        $next = static::where('booking_id', 'like', $prefix . '%')->count() + 1;

        return $prefix . str_pad($next, 3, '0', STR_PAD_LEFT);
    }
}
