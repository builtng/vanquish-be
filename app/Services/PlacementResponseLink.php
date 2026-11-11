<?php

namespace App\Services;

use App\Models\QcApplication;
use App\Models\TraineeApplication;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * The "Accept" link in the acceptance email. Clicking it opens
 * /placement-response/{token}, where the applicant accepts the offer and
 * confirms they will attend induction. The link works for 72 hours.
 */
class PlacementResponseLink
{
    public const VALID_HOURS = 72;

    /**
     * Issue a fresh link and return the email placeholders for it.
     *
     * @return array{acceptance_link: string, placement_response_link: string, accept_by: string}
     */
    public function issue(Model $application): array
    {
        $token = $application->placement_response_token ?: Str::random(40);

        $application->forceFill([
            'placement_response_token' => $token,
            'placement_response_sent_at' => now(),
        ])->save();

        $url = rtrim(config('app.frontend_url') ?: 'https://vqtmanagement.com', '/') . '/placement-response/' . $token;
        $acceptBy = now()->addHours(self::VALID_HOURS)->timezone('Europe/London')->format('l j F Y, g:ia');

        return [
            'acceptance_link' => $url,
            'placement_response_link' => $url,
            'accept_by' => $acceptBy,
        ];
    }

    public static function find(string $token): ?Model
    {
        return TraineeApplication::where('placement_response_token', $token)->first()
            ?? QcApplication::where('placement_response_token', $token)->first();
    }

    public static function isExpired(Model $application): bool
    {
        $sentAt = $application->placement_response_sent_at;
        if (!$sentAt) {
            return false; // links sent before expiry was introduced
        }

        return \Carbon\Carbon::parse($sentAt)->addHours(self::VALID_HOURS)->isPast();
    }
}
