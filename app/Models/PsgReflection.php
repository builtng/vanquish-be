<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PsgReflection extends Model
{
    protected $fillable = [
        'training_counsellor_id',
        'attendance_date',
        'reflection',
        'status'
    ];

    protected $casts = [
        'attendance_date' => 'date',
    ];

    public function counsellor(): BelongsTo
    {
        return $this->belongsTo(TrainingCounsellor::class, 'training_counsellor_id');
    }
}
