<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Person extends Model
{
    protected $table = 'persons';

    protected $fillable = [
        'uuid',
        'email',
        'first_name',
        'last_name',
        'name',
        'phone',
        'type',
        'archived_at',
    ];

    protected $casts = [
        'archived_at' => 'datetime',
    ];

    protected static function booted()
    {
        static::creating(function ($person) {
            if (empty($person->uuid)) {
                $person->uuid = (string) Str::uuid();
            }
            if (!empty($person->email)) {
                $person->email = strtolower(trim($person->email));
            }
        });
    }

    /**
     * Get or create a person identity by email safely.
     */
    public static function findOrCreateByEmail(string $email, array|string $attributes = [], ?string $phone = null): self
    {
        $normalized = strtolower(trim($email));

        if (is_string($attributes)) {
            $name = trim($attributes);
            $nameParts = explode(' ', $name, 2);
            $attributes = [
                'name' => $name,
                'first_name' => $nameParts[0] ?? null,
                'last_name' => $nameParts[1] ?? null,
                'phone' => $phone,
            ];
        }

        $person = static::where('email', $normalized)->first();

        if ($person) {
            // Update name or phone if provided and not empty
            $updates = [];
            if (!empty($attributes['name']) && empty($person->name)) {
                $updates['name'] = trim($attributes['name']);
            }
            if (!empty($attributes['phone']) && empty($person->phone)) {
                $updates['phone'] = trim($attributes['phone']);
            }
            if (!empty($attributes['first_name']) && empty($person->first_name)) {
                $updates['first_name'] = trim($attributes['first_name']);
            }
            if (!empty($attributes['last_name']) && empty($person->last_name)) {
                $updates['last_name'] = trim($attributes['last_name']);
            }
            if (!empty($updates)) {
                $person->update($updates);
            }
            return $person;
        }

        $fullName = !empty($attributes['name'])
            ? trim($attributes['name'])
            : trim(($attributes['first_name'] ?? '') . ' ' . ($attributes['last_name'] ?? ''));

        return static::create([
            'uuid' => (string) Str::uuid(),
            'email' => $normalized,
            'first_name' => $attributes['first_name'] ?? null,
            'last_name' => $attributes['last_name'] ?? null,
            'name' => $fullName ?: $normalized,
            'phone' => $attributes['phone'] ?? null,
            'type' => $attributes['type'] ?? 'client',
        ]);
    }

    public function clients(): HasMany
    {
        return $this->hasMany(Client::class, 'person_id')->orderByDesc('id');
    }

    public function traineeApplications(): HasMany
    {
        return $this->hasMany(TraineeApplication::class, 'person_id')->orderByDesc('id');
    }

    public function qcApplications(): HasMany
    {
        return $this->hasMany(QcApplication::class, 'person_id')->orderByDesc('id');
    }

    public function trainingCounsellors(): HasMany
    {
        return $this->hasMany(TrainingCounsellor::class, 'person_id')->orderByDesc('id');
    }

    /**
     * Active (non-archived) scope
     */
    public function scopeActive($query)
    {
        return $query->whereNull('archived_at');
    }
}
