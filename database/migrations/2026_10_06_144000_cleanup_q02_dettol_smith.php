<?php

use Illuminate\Database\Migrations\Migration;
use App\Console\Commands\CleanupQ02DettolSmith;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $result = CleanupQ02DettolSmith::executeCleanup(true);
        $json = json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

        \Illuminate\Support\Facades\Log::info("Q02_CLEANUP_RESULT:\n" . $json);
        echo "\n=== Q02_CLEANUP_START ===\n" . $json . "\n=== Q02_CLEANUP_END ===\n";
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // One-time cleanup migration
    }
};
