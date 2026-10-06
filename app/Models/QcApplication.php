<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class QcApplication extends Model
{
    use SoftDeletes;

    protected $table = 'qc_applications';

    protected $fillable = [
        'uuid',
        'person_id',
        'training_counsellor_id',
        'suggested_training_counsellor_id',
        'legal_first_name',
        'legal_last_name',
        'name',
        'email',
        'phone',
        'previous_vanquish_work',
        'areas_to_improve',
        'other_modalities',
        'other_experience_areas',
        'status',
        'answers',
        'qualification_document',
        'dbs_certificate_qualified',
        'insurance_qualified',
        'self_employment_proof',
        'professional_membership',
        'valid_id_document',
        'signature',
        'signature_date',
        'notes',
        'archived_at',
    ];

    protected $casts = [
        'answers' => 'array',
        'signature_date' => 'date',
        'archived_at' => 'datetime',
    ];

    public function getAreasToImproveAttribute($value): ?string
    {
        return $value ?? ($this->answers['areas_to_improve'] ?? null);
    }

    public function getPreviousVanquishWorkAttribute($value): ?string
    {
        return $value ?? ($this->answers['previous_vanquish_work'] ?? null);
    }

    public function getOtherModalitiesAttribute($value): ?string
    {
        return $value ?? ($this->answers['other_modalities'] ?? null);
    }

    public function getOtherExperienceAreasAttribute($value): ?string
    {
        return $value ?? ($this->answers['other_experience_areas'] ?? null);
    }

    protected static function booted()
    {
        static::creating(function ($app) {
            if (empty($app->uuid)) {
                $app->uuid = (string) Str::uuid();
            }
            if (!empty($app->email)) {
                $app->email = strtolower(trim($app->email));
            }
            if (empty($app->name)) {
                $app->name = trim(($app->legal_first_name ?? '') . ' ' . ($app->legal_last_name ?? ''));
            }
        });
    }

    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class, 'person_id');
    }

    public function trainingCounsellor(): BelongsTo
    {
        return $this->belongsTo(TrainingCounsellor::class, 'training_counsellor_id');
    }

    public function suggestedTrainingCounsellor(): BelongsTo
    {
        return $this->belongsTo(TrainingCounsellor::class, 'suggested_training_counsellor_id');
    }

    public function scopeActive($query)
    {
        return $query->whereNull('archived_at');
    }
}
