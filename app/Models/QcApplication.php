<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class QcApplication extends Model
{
    protected $table = 'qc_applications';

    protected $fillable = [
        'uuid',
        'person_id',
        'training_counsellor_id',
        'legal_first_name',
        'legal_last_name',
        'name',
        'email',
        'phone',
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

    public function scopeActive($query)
    {
        return $query->whereNull('archived_at');
    }
}
