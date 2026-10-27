<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class PersonalTherapyToken extends Model
{
    use HasFactory;

    protected $table = 'personal_therapy_tokens';

    protected $fillable = [
        'trainee_application_id',
        'token',
        'signature',
        'expires_at',
        'used_at',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'used_at' => 'datetime',
    ];

    public function traineeApplication(): BelongsTo
    {
        return $this->belongsTo(TraineeApplication::class, 'trainee_application_id');
    }

    /**
     * Create a signed, single-use token tied to a trainee application, valid for 30 days.
     */
    public static function createFor(TraineeApplication $application, int $daysValid = 30): self
    {
        $expiresAt = now()->addDays($daysValid);
        $random = Str::random(32);
        $payload = "{$application->id}|{$expiresAt->timestamp}|{$random}";
        $signature = hash_hmac('sha256', $payload, (string) config('app.key', 'vanquish-secret'));
        $token = Str::random(32) . bin2hex(random_bytes(16));

        return self::create([
            'trainee_application_id' => $application->id,
            'token' => $token,
            'signature' => $signature,
            'expires_at' => $expiresAt,
            'used_at' => null,
        ]);
    }

    /**
     * Find a valid, unused, and unexpired token.
     * Returns null if token is not found, expired, or already used.
     */
    public static function findValid(?string $token): ?self
    {
        if (empty($token) || !is_string($token)) {
            return null;
        }

        $record = self::with('traineeApplication')->where('token', trim($token))->first();

        if (!$record) {
            return null;
        }

        if ($record->used_at !== null) {
            return null;
        }

        if ($record->expires_at && $record->expires_at->isPast()) {
            return null;
        }

        if (!$record->traineeApplication) {
            return null;
        }

        return $record;
    }

    /**
     * Mark token as used upon successful confirmation.
     */
    public function markAsUsed(): void
    {
        $this->update(['used_at' => now()]);
    }
}
