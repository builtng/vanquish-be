<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PsgSessionAttendee extends Model
{
    protected $fillable = [
        'psg_session_log_id',
        'training_counsellor_id',
        'attended',
        'comment',
    ];

    protected $casts = [
        'attended' => 'boolean',
    ];

    public function sessionLog(): BelongsTo
    {
        return $this->belongsTo(PsgSessionLog::class, 'psg_session_log_id');
    }

    public function trainingCounsellor(): BelongsTo
    {
        return $this->belongsTo(TrainingCounsellor::class, 'training_counsellor_id');
    }
}
