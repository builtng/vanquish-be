<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AttendanceGroup extends Model
{
    protected $fillable = [
        'name',
        'supervisor_link',
        'supervisor_name',
        'supervisor_email',
        'day_of_week',
        'is_active',
        'public_token',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function trainingCounsellors(): HasMany
    {
        return $this->hasMany(TrainingCounsellor::class, 'attendance_group_id');
    }

    public function discussions(): HasMany
    {
        return $this->hasMany(PsgGroupDiscussion::class, 'attendance_group_id');
    }

    protected static function booted()
    {
        static::creating(function ($group) {
            if (empty($group->public_token)) {
                $group->public_token = \Illuminate\Support\Str::uuid()->toString();
            }
        });
    }
}
