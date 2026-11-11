<?php

use App\Models\TrainingCounsellor;
use App\Services\PlacementProfileFiller;
use Illuminate\Database\Migrations\Migration;

/**
 * Trainees approved before this fix have "No availability set" and no topics
 * on their profile even though they answered those questions on the
 * placement application. Fill the empty fields from their application.
 */
return new class extends Migration
{
    public function up(): void
    {
        $filler = app(PlacementProfileFiller::class);

        TrainingCounsellor::whereNull('archived_at')
            ->where('counsellor_type', 'Trainee')
            ->orderBy('id')
            ->each(function (TrainingCounsellor $tc) use ($filler) {
                try {
                    $filler->fill($tc);
                } catch (\Throwable $e) {
                    \Illuminate\Support\Facades\Log::warning("Placement profile backfill skipped for TC {$tc->id}: " . $e->getMessage());
                }
            });
    }

    public function down(): void
    {
        // Data backfill only; nothing to undo.
    }
};
