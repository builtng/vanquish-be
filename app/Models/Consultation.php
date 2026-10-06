<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Consultation extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'consultation_id',
        'client_id',
        'tc_id',
        'scheduled_at',
        'completed_at',
        'status',
        'cancelled_at',
        'cancelled_by',
        'archived_at',
        'duration_minutes',
        'notes',
        'outcome',
        'recommended_service',
        'recommended_modality',
        'risk_notes',
        'next_steps',
        'send_confirmation',
        'payment_status',
        'payment_amount',
        'stripe_payment_intent_id',
        'stripe_customer_id',
        'paid_at',
        'payment_method',
        'is_fallback',
        'consultation_slot_id',
    ];

    protected $casts = [
        'scheduled_at' => 'datetime',
        'completed_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'archived_at' => 'datetime',
        'send_confirmation' => 'boolean',
        'paid_at' => 'datetime',
        'payment_amount' => 'decimal:2',
        'is_fallback' => 'boolean',
    ];

    public function cancelledByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function tc(): BelongsTo
    {
        return $this->belongsTo(TrainingCounsellor::class, 'tc_id');
    }

    public function consultationSlot(): BelongsTo
    {
        return $this->belongsTo(ConsultationSlot::class);
    }

    public static function generateNextConsultationId(): string
    {
        $query = method_exists(static::class, 'withTrashed') ? static::withTrashed() : static::query();
        $ids = $query->pluck('consultation_id');
        $maxNum = 0;

        foreach ($ids as $id) {
            if (preg_match('/^(?:CONS|CON)(\d+)$/i', $id, $matches)) {
                $num = intval($matches[1]);
                if ($num > $maxNum) {
                    $maxNum = $num;
                }
            }
        }

        $nextNum = max($maxNum + 1, $query->count() + 1);

        do {
            $candidate = 'CONS' . str_pad($nextNum, 3, '0', STR_PAD_LEFT);
            $nextNum++;
            $checkQuery = method_exists(static::class, 'withTrashed') ? static::withTrashed() : static::query();
        } while ($checkQuery->where('consultation_id', $candidate)->exists());

        return $candidate;
    }

    protected static function booted()
    {
        static::creating(function ($consultation) {
            if (empty($consultation->consultation_id)) {
                $consultation->consultation_id = static::generateNextConsultationId();
            }
        });

        static::saved(function ($consultation) {
            $consultation->syncRelatedSlots();
        });

        static::deleted(function ($consultation) {
            $consultation->syncRelatedSlots();
        });
    }

    public function syncRelatedSlots()
    {
        $slotIds = [];

        if ($this->consultation_slot_id) {
            $slotIds[] = $this->consultation_slot_id;
        }

        if ($this->isDirty('consultation_slot_id') && $this->getOriginal('consultation_slot_id')) {
            $slotIds[] = $this->getOriginal('consultation_slot_id');
        }

        if ($this->scheduled_at) {
            $slotsByTime = ConsultationSlot::where('consultation_datetime', $this->scheduled_at)->pluck('id')->toArray();
            $slotIds = array_merge($slotIds, $slotsByTime);
        }

        if ($this->isDirty('scheduled_at') && $this->getOriginal('scheduled_at')) {
            $slotsByOldTime = ConsultationSlot::where('consultation_datetime', $this->getOriginal('scheduled_at'))->pluck('id')->toArray();
            $slotIds = array_merge($slotIds, $slotsByOldTime);
        }

        $slotIds = array_unique($slotIds);

        foreach ($slotIds as $slotId) {
            $slot = ConsultationSlot::find($slotId);
            if ($slot) {
                // Dynamically count valid consultations
                $count = Consultation::where(function ($q) use ($slot) {
                    $q->where('consultation_slot_id', $slot->id)
                        ->orWhere('scheduled_at', $slot->consultation_datetime);
                })
                    ->whereIn('status', ['scheduled', 'completed'])
                    ->count();

                $needsSave = false;

                if ($slot->booked_slots !== $count) {
                    $slot->booked_slots = $count;
                    $needsSave = true;
                }

                if ($slot->max_slots && $slot->booked_slots >= $slot->max_slots) {
                    if ($slot->status === 'available') {
                        $slot->status = 'full';
                        $needsSave = true;
                    }
                } elseif ($slot->status === 'full' && (!$slot->max_slots || $slot->booked_slots < $slot->max_slots)) {
                    $slot->status = 'available';
                    $needsSave = true;
                }

                if ($needsSave) {
                    $slot->saveQuietly(); // Use saveQuietly to prevent redundant event triggers
                }
            }
        }
    }
}
