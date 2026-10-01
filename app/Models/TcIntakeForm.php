<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TcIntakeForm extends Model
{
    protected $fillable = [
        'tc_id',
        'name',
        'email',
        'phone',
        'modality',
        'course',
        'institution',
        'topics_with_experience',
        'topics_not_ready_for',
        'availability',
        'additional_info',
        'fitness_to_practice',
        'qualifications',
        'dbs_certificate',
        'cv',
        'valid_id',
        'insurance_certificate',
        'status',
    ];

    protected $casts = [
        'topics_with_experience' => 'array',
        'topics_not_ready_for' => 'array',
        'availability' => 'array',
    ];

    public function tc(): BelongsTo
    {
        return $this->belongsTo(TrainingCounsellor::class, 'tc_id');
    }
}
