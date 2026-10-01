<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;

class TrainingCounsellor extends Model
{
    use SoftDeletes;

    protected $with = ['user', 'attendanceGroup'];

    /**
     * Get the user associated with this training counsellor
     */
    public function user(): HasOne
    {
        return $this->hasOne(User::class, 'training_counsellor_id');
    }

    /**
     * Get the assigned Peer Support Group (Attendance Group)
     */
    public function attendanceGroup()
    {
        return $this->belongsTo(AttendanceGroup::class, 'attendance_group_id');
    }

    public function person()
    {
        return $this->belongsTo(Person::class, 'person_id');
    }

    public function scopeActive($query)
    {
        return $query->whereNull('archived_at');
    }

    /**
     * Date ranges this counsellor is unavailable (e.g. holidays).
     */
    public function holidays(): HasMany
    {
        return $this->hasMany(TrainingCounsellorHoliday::class, 'tc_id');
    }

    protected $fillable = [
        'person_id',
        'uuid',
        'tc_id',
        'attendance_group_id',
        'name',
        'email',
        'photo',
        'phone',
        'gender',
        'ethnicity',
        'sexual_orientation',
        'age',
        'date_of_birth',
        'address',
        'modality',
        'session_price',
        'bio',
        'offers_mid_range',
        'offers_coaching',
        'status',
        'counsellor_type',
        'current_clients',
        'availability',
        'topics_with_experience',
        'topics_not_ready_for',
        'course',
        'institution',
        'training_org_address',
        'tutor_name',
        'tutor_email',
        'tutor_phone',
        'placement_lead_name',
        'placement_lead_email',
        'placement_lead_phone',
        'joined_date',
        'last_activity',
        // Qualified Counsellor fields
        'legal_first_name',
        'legal_last_name',
        'registered_address',
        'registered_city',
        'registered_postcode',
        'has_supervisor',
        'previous_vanquish_work',
        'areas_to_improve',
        'unique_trait',
        'counsellor_training_details',
        'qualified_to_work_with',
        'challenging_cases',
        'qualification_document',
        'dbs_certificate_qualified',
        'insurance_qualified',
        'self_employment_proof',
        'professional_membership',
        'signature',
        'signature_date',
        'qualified_form_completed',
        // Consultation delegation (Mid Range / Coaching & Counselling)
        'consultation_availability',
        'show_own_consultation_availability',
        'show_vanquish_consultation_availability',
        'archived_at',
    ];

    protected $casts = [
        'availability' => 'array',
        'topics_with_experience' => 'array',
        'topics_not_ready_for' => 'array',
        'qualified_to_work_with' => 'array',
        'joined_date' => 'date',
        'signature_date' => 'date',
        'last_activity' => 'datetime',
        'qualified_form_completed' => 'boolean',
        'session_price' => 'decimal:2',
        'offers_mid_range' => 'boolean',
        'offers_coaching' => 'boolean',
        'consultation_availability' => 'array',
        'show_own_consultation_availability' => 'boolean',
        'show_vanquish_consultation_availability' => 'boolean',
        'archived_at' => 'datetime',
    ];

    protected $appends = [
        'photo_url',
        'abbreviated_name',
        'first_name',
    ];

    /**
     * Get the first name of the counsellor.
     */
    public function getFirstNameAttribute()
    {
        if (!empty($this->attributes['legal_first_name'])) {
            return $this->attributes['legal_first_name'];
        }
        if (!empty($this->attributes['name'])) {
            $cleaned = preg_replace('/^(Dr|Mr|Mrs|Ms|Miss|Prof)\.?\s+/i', '', trim($this->attributes['name']));
            return explode(' ', $cleaned)[0];
        }
        return '';
    }

    /**
     * Get the full public URL for the counsellor photo.
     */
    public function getPhotoUrlAttribute()
    {
        if (!$this->photo) {
            return null;
        }

        if (str_starts_with($this->photo, 'http://') || str_starts_with($this->photo, 'https://')) {
            return $this->photo;
        }

        $appUrl = rtrim(config('app.url'), '/');
        return $appUrl . '/storage/' . ltrim($this->photo, '/');
    }

    /**
     * Get the abbreviated name (e.g., "John Smith" -> "John S.")
     */
    public function getAbbreviatedNameAttribute()
    {
        if (!$this->name) {
            return '';
        }

        $parts = explode(' ', trim($this->name));
        if (count($parts) > 1) {
            $lastName = array_pop($parts);
            return implode(' ', $parts) . ' ' . substr($lastName, 0, 1) . '.';
        }

        return $this->name;
    }

    public function clients(): HasMany
    {
        return $this->hasMany(Client::class, 'matched_tc_id');
    }

    public function consultations(): HasMany
    {
        return $this->hasMany(Consultation::class, 'tc_id');
    }

    public function matches(): HasMany
    {
        return $this->hasMany(ClientTcMatch::class, 'tc_id');
    }

    public function intakeForm(): HasMany
    {
        return $this->hasMany(TcIntakeForm::class, 'tc_id');
    }

    /**
     * Admin-logged conduct/reliability events (late to session, missed PSG,
     * etc.) that feed the trainee's progress bar.
     */
    public function conductEvents(): HasMany
    {
        return $this->hasMany(TcConductEvent::class, 'tc_id')->orderByDesc('id');
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

        // Try UUID first, then fall back to tc_id for backward compatibility
        return $this->where($field, $value)
            ->orWhere('tc_id', $value)
            ->first();
    }

    /**
     * Generate a guaranteed collision-free TC ID (e.g. TC001, TC002, ...)
     */
    public static function generateUniqueTcId(): string
    {
        // Check ALL records including soft-deleted ones to avoid unique key collisions
        $allExisting = static::withTrashed()
            ->whereNotNull('tc_id')
            ->pluck('tc_id')
            ->all();

        $maxNumber = 0;
        $existingSet = [];

        foreach ($allExisting as $existingId) {
            $normalized = strtoupper(trim($existingId));
            $existingSet[$normalized] = true;

            // Extract numeric portion from formats like TC001, TC-001, TC_12, etc.
            if (preg_match('/(\d+)/', $existingId, $matches)) {
                $num = (int) $matches[1];
                if ($num > $maxNumber) {
                    $maxNumber = $num;
                }
            }
        }

        $nextNum = max($maxNumber + 1, count($allExisting) + 1);

        do {
            $candidate = 'TC' . str_pad($nextNum, 3, '0', STR_PAD_LEFT);
            $nextNum++;
        } while (isset($existingSet[$candidate]) || static::withTrashed()->where('tc_id', $candidate)->exists());

        return $candidate;
    }

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($tc) {
            if (empty($tc->uuid)) {
                $tc->uuid = Str::uuid()->toString();
            }
            if (empty($tc->tc_id)) {
                $tc->tc_id = static::generateUniqueTcId();
            }
        });
    }
}
