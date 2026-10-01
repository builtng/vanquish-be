<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PsgGroupDiscussion extends Model
{
    protected $fillable = [
        'attendance_group_id',
        'user_id',
        'message',
    ];

    public function attendanceGroup(): BelongsTo
    {
        return $this->belongsTo(AttendanceGroup::class, 'attendance_group_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
