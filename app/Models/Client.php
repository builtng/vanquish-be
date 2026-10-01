<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Client extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'uuid',
        'client_id',
        'name',
        'person_id',
        'first_name',
        'last_name',
        'age',
        'email',
        'photo',
        'phone',
        'address',
        'postcode',
        'gender',
        'ethnicity',
        'sexual_orientation',
        'gender_preference',
        'age_preference',
        'ethnicity_preference',
        'orientation_preference',
        'stage',
        'status',
        'service_type',
        'primary_issues',
        'additional_details',
        'on_medication',
        'medication_details',
        'medication',
        'disabilities',
        'risk_issues',
        'risk_flags',
        'substance_misuse',
        'availability',
        'voicemail_permission',
        'currently_in_therapy',
        'how_heard_about',
        'referral_reasons',
        'referral_name',
        'referral_phone',
        'organization_name',
        'organization_email',
        'admin_notes',
        'payment_status',
        'submitted_date',
        'start_date',
        'sessions_completed',
        'matched_tc_id',
        'matched_date',
        'agreement_status',
        'agreement_sent_at',
        'agreement_signed_at',
        'agreement_jotform_id',
        'agreement_signature_url',
        'emergency_contact_name',
        'emergency_contact_phone',
        'emergency_contact_email',
        'emergency_contact_relationship',
        'gp_name',
        'gp_practice_name',
        'gp_practice_phone',
        'current_address',
        'case_study_consent',
        'last_feedback_sent_at',
        'last_feedback_date',
        'satisfaction_score',
        'feedback_count',
        'allocated_day',
        'allocated_time',
        'next_booking_deadline',
        'jotform_intake_data',
        'jotform_intake_submission_id',
        'jotform_intake_completed_at',
        'whatsapp_agreement',
        'partner_email',
        'partner_phone',
        'working_with_another_reason',
        'location_of_residence',
        'referral_type',
        'core34_answers',
        'is_couples',
        'partner_first_name',
        'partner_last_name',
        'partner_age',
        'partner_gender',
        'partner_ethnicity',
        'partner_sexual_orientation',
        'partner_on_medication',
        'partner_medication_details',
        'partner_disabilities',
        'preferred_tc_id',
        'archived_at',
    ];

    public function person()
    {
        return $this->belongsTo(Person::class, 'person_id');
    }

    public function scopeActive($query)
    {
        return $query->whereNull('archived_at');
    }

    protected $appends = [
        'abbreviated_name',
    ];

    protected $casts = [
        'primary_issues' => 'array',
        'availability' => 'array',
        'submitted_date' => 'date',
        'start_date' => 'date',
        'matched_date' => 'date',
        'agreement_sent_at' => 'datetime',
        'agreement_signed_at' => 'datetime',
        'last_feedback_sent_at' => 'date',
        'last_feedback_date' => 'date',
        'satisfaction_score' => 'decimal:2',
        'next_booking_deadline' => 'date',
        'jotform_intake_data' => 'array',
        'jotform_intake_completed_at' => 'datetime',
        'core34_answers' => 'array',
        'is_couples' => 'boolean',
        'partner_on_medication' => 'boolean',
        'archived_at' => 'datetime',
    ];

    public function matchedTc(): BelongsTo
    {
        return $this->belongsTo(TrainingCounsellor::class, 'matched_tc_id');
    }

    public function preferredTc(): BelongsTo
    {
        return $this->belongsTo(TrainingCounsellor::class, 'preferred_tc_id');
    }

    public function consultations(): HasMany
    {
        return $this->hasMany(Consultation::class);
    }

    public function matches(): HasMany
    {
        return $this->hasMany(ClientTcMatch::class);
    }

    public function intakeForm(): HasMany
    {
        return $this->hasMany(ClientIntakeForm::class);
    }

    public function sessions(): HasMany
    {
        return $this->hasMany(Session::class);
    }

    /**
     * Get the next session that needs booking (for Low Cost clients)
     */
    public function getNextSessionNeedingBooking()
    {
        if ($this->service_type !== 'Low Cost') {
            return null;
        }

        // Find the next scheduled session that's 48+ hours away
        return $this->sessions()
            ->where('status', 'scheduled')
            ->where('scheduled_at', '>=', now()->addHours(48))
            ->whereNull('booking_deadline') // Not yet booked
            ->orderBy('scheduled_at')
            ->first();
    }

    /**
     * Get upcoming sessions for this client
     */
    public function getUpcomingSessions($limit = 10)
    {
        return $this->sessions()
            ->where('status', 'scheduled')
            ->where('scheduled_at', '>=', now())
            ->orderBy('scheduled_at')
            ->limit($limit)
            ->get();
    }

    /**
     * Get the route key for the model.
     *
     * @return string
     */
    public function getRouteKeyName()
    {
        return 'uuid';
    }

    /**
     * Retrieve the model for bound value.
     *
     * @param  mixed  $value
     * @param  string|null  $field
     * @return \Illuminate\Database\Eloquent\Model|null
     */
    public function resolveRouteBinding($value, $field = null)
    {
        $field = $field ?: $this->getRouteKeyName();

        // Try UUID first, then fall back to client_id for backward compatibility
        return $this->where($field, $value)
            ->orWhere('client_id', $value)
            ->first();
    }

    /**
     * Generate the next unique client_id (e.g. CL001, CL002)
     */
    public static function generateNextClientId(): string
    {
        $latestClient = static::withTrashed()
            ->where('client_id', 'LIKE', 'CL%')
            ->orderBy('id', 'desc')
            ->first();

        $nextNum = 1;
        if ($latestClient && preg_match('/^CL(\d+)$/', $latestClient->client_id, $matches)) {
            $nextNum = intval($matches[1]) + 1;
        } else {
            $nextNum = static::withTrashed()->count() + 1;
        }

        $newClientId = 'CL' . str_pad($nextNum, 3, '0', STR_PAD_LEFT);

        while (static::withTrashed()->where('client_id', $newClientId)->exists()) {
            $nextNum++;
            $newClientId = 'CL' . str_pad($nextNum, 3, '0', STR_PAD_LEFT);
        }

        return $newClientId;
    }

    /**
     * Get other cases sharing the same email under this person
     */
    public function getRelatedCases()
    {
        if (empty($this->email)) {
            return collect();
        }

        return static::where('email', $this->email)
            ->where('id', '!=', $this->id)
            ->orderBy('id', 'desc')
            ->get();
    }

    /**
     * Get the abbreviated name (e.g., "Charles Smith" -> "Charles .S")
     */
    public function getAbbreviatedNameAttribute(): string
    {
        $name = trim((string) ($this->name ?? ''));
        if ($name === '') {
            $first = trim((string) ($this->first_name ?? ''));
            $last = trim((string) ($this->last_name ?? ''));
            $name = trim("{$first} {$last}");
        }

        if ($name === '') {
            return '';
        }

        $parts = preg_split('/\s+/', $name);
        if (count($parts) > 1) {
            $firstName = $parts[0];
            $lastName = end($parts);
            return $firstName . ' .' . strtoupper(substr($lastName, 0, 1));
        }

        return $name;
    }

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($client) {
            if (empty($client->uuid)) {
                $client->uuid = Str::uuid()->toString();
            }
            if (empty($client->client_id)) {
                $client->client_id = static::generateNextClientId();
            }
        });
    }
}
