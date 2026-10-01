<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class TraineeApplication extends Model
{
    use SoftDeletes;

    protected $fillable = [
        // Core identification
        'person_id',
        'uuid',
        'source',
        'status',
        'archived_at',
        'jotform_submission_id',
        'jotform_submitted_at',
        'jotform_raw_data',

        // Personal information
        'first_name',
        'last_name',
        'date_of_birth',
        'gender',
        'ethnicity',
        'ethnicity_other',
        'email',
        'phone',
        'sexual_orientation',
        'sexual_orientation_other',
        'address',
        'beliefs',
        'beliefs_other',
        'disabilities',
        'medical_conditions',

        // Fitness to Practise
        'has_insurance',
        'professional_body_member',
        'professional_body_details',
        'dbs_update_service',
        'dbs_update_details',
        'in_personal_therapy',
        'personal_therapy_reason',
        'has_clinical_supervisor',
        'supervisor_reason',
        'previous_online_counselling',
        'availability_schedule',
        'criminal_convictions',

        // Course information
        'institution',
        'college_address',
        'tutor_name',
        'tutor_email',
        'tutor_phone',
        'placement_lead_name',
        'placement_lead_phone',
        'placement_lead_email',
        'course_name',
        'course_title',
        'course_duration',
        'expected_qualification_date',
        'counselling_type',
        'face_to_face_requirement',
        'has_face_to_face_clients',
        'face_to_face_client_count',
        'face_to_face_hours_completed',
        'skills_practice_details',

        // Experience & journey
        'experience_background',
        'family_impact',
        'personal_journey',
        'self_awareness',
        'training_history',
        'areas_of_experience',
        'personal_development_areas',
        'theoretical_approach',

        // Peer Support Group
        'psg_day_preference',
        'psg_reason',

        // Document uploads (stored as URLs from JotForm CDN)
        'doc_fitness_to_practise',
        'doc_prior_qualifications',
        'doc_dbs_certificate',
        'doc_cv',
        'doc_valid_id',
        'doc_indemnity_insurance',

        'video_url',
        'interview_data',
        'onboarding_data',
        'induction_date',
        'portal_access_granted',
        'assessment_answers',

        // Placement response (accept/decline + induction RSVP)
        'placement_response_token',
        'placement_accepted',
        'induction_rsvp',
        'placement_responded_at',

        // Disclaimer
        'disclaimer_date',
    ];

    protected $casts = [
        'assessment_answers' => 'array',
        'jotform_raw_data'   => 'array',
        'interview_data'     => 'array',
        'onboarding_data'    => 'array',
        'disclaimer_date'    => 'date',
        'jotform_submitted_at' => 'datetime',
        'portal_access_granted' => 'boolean',
        'placement_accepted' => 'boolean',
        'induction_rsvp' => 'boolean',
        'placement_responded_at' => 'datetime',
    ];

    /**
     * Virtual full-name accessor.
     */
    public function getNameAttribute(): string
    {
        return "{$this->first_name} {$this->last_name}";
    }

    /**
     * Auto-generate UUID on create.
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if (empty($model->uuid)) {
                $model->uuid = (string) Str::uuid();
            }
        });
    }

    /**
     * Returns true when at least one document URL is present.
     */
    public function hasDocuments(): bool
    {
        return !empty($this->doc_cv)
            || !empty($this->doc_valid_id)
            || !empty($this->doc_dbs_certificate)
            || !empty($this->doc_prior_qualifications)
            || !empty($this->doc_fitness_to_practise)
            || !empty($this->doc_indemnity_insurance);
    }

    public function person()
    {
        return $this->belongsTo(Person::class, 'person_id');
    }

    public function scopeActive($query)
    {
        return $query->whereNull('archived_at');
    }
}
