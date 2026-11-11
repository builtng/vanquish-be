<?php

namespace App\Services;

use App\Models\TcIntakeForm;
use App\Models\TraineeApplication;
use App\Models\TrainingCounsellor;

/**
 * Copies the answers a trainee gave on their placement application
 * (availability, topics they have experience with, topics they are not
 * ready for, modality) onto their counsellor profile.
 *
 * Only empty profile fields are filled, so anything an admin has already
 * edited is never overwritten.
 */
class PlacementProfileFiller
{
    public function fill(TrainingCounsellor $tc, ?TraineeApplication $application = null): bool
    {
        $email = strtolower(trim((string) $tc->email));
        if ($email === '') {
            return false;
        }

        $intake = TcIntakeForm::whereRaw('LOWER(email) = ?', [$email])->latest('id')->first();

        $availability = $intake?->availability;
        if (empty($availability)) {
            $application ??= TraineeApplication::whereRaw('LOWER(email) = ?', [$email])->latest('id')->first();
            $availability = $this->decode($application?->availability_schedule);
        }

        $updates = [];
        if (empty($tc->availability) && !empty($availability)) {
            $updates['availability'] = $availability;
        }
        if (empty($tc->topics_with_experience) && !empty($intake?->topics_with_experience)) {
            $updates['topics_with_experience'] = $intake->topics_with_experience;
        }
        if (empty($tc->topics_not_ready_for) && !empty($intake?->topics_not_ready_for)) {
            $updates['topics_not_ready_for'] = $intake->topics_not_ready_for;
        }
        if (empty($tc->modality) && !empty($intake?->modality)) {
            $updates['modality'] = $intake->modality;
        }

        if (empty($updates)) {
            return false;
        }

        $tc->update($updates);

        return true;
    }

    private function decode($value): array
    {
        if (is_array($value)) {
            return $value;
        }
        if (is_string($value) && $value !== '') {
            $decoded = json_decode($value, true);
            // Stored twice-encoded by some older submissions.
            if (is_string($decoded)) {
                $decoded = json_decode($decoded, true);
            }
            return is_array($decoded) ? $decoded : [];
        }
        return [];
    }
}
