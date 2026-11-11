<?php

use App\Services\OrphanedMatchRepair;
use Illuminate\Database\Migrations\Migration;

/**
 * Clients showing "Matched With Counsellor" but "Not assigned" (their
 * counsellor was archived) go back to Pending Matches.
 */
return new class extends Migration
{
    public function up(): void
    {
        app(OrphanedMatchRepair::class)->releaseAllOrphans();
    }

    public function down(): void
    {
        // Data repair only.
    }
};
