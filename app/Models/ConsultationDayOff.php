<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ConsultationDayOff extends Model
{
    protected $table = 'consultation_days_off';

    protected $fillable = [
        'date',
        'reason',
        'created_by',
    ];

    protected $casts = [
        'date' => 'date:Y-m-d',
    ];

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
