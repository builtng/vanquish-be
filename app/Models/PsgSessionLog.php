<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PsgSessionLog extends Model
{
    protected $fillable = [
        'attendance_group_id',
        'session_date',
        'supervisor_name',
        'activities',
        'notes',
    ];

    protected $casts = [
        'session_date' => 'date',
    ];

    public function attendanceGroup(): BelongsTo
    {
        return $this->belongsTo(AttendanceGroup::class, 'attendance_group_id');
    }

    public function attendees(): HasMany
    {
        return $this->hasMany(PsgSessionAttendee::class, 'psg_session_log_id');
    }
}
