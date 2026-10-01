<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ConsultationSlot extends Model
{
    protected $fillable = [
        'consultation_datetime',
        'max_slots',
        'booked_slots',
        'status',
        'type',
        'zoom_link',
        'host_name',
    ];

    protected $casts = [
        'consultation_datetime' => 'datetime',
        'max_slots' => 'integer',
        'booked_slots' => 'integer',
    ];

    public function consultations()
    {
        return $this->hasMany(Consultation::class);
    }
}
