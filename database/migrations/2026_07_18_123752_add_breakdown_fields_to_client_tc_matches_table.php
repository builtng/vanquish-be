<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('client_tc_matches', function (Blueprint $table) {
            if (!Schema::hasColumn('client_tc_matches', 'matched_by')) {
                $table->foreignId('matched_by')->nullable()->after('tc_id')->constrained('users')->nullOnDelete();
            }
            if (!Schema::hasColumn('client_tc_matches', 'match_breakdown')) {
                $table->json('match_breakdown')->nullable()->after('match_score');
            }
            if (!Schema::hasColumn('client_tc_matches', 'flags')) {
                $table->json('flags')->nullable()->after('match_breakdown');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('client_tc_matches', function (Blueprint $table) {
            if (Schema::hasColumn('client_tc_matches', 'matched_by')) {
                $table->dropConstrainedForeignId('matched_by');
            }
            if (Schema::hasColumn('client_tc_matches', 'match_breakdown')) {
                $table->dropColumn('match_breakdown');
            }
            if (Schema::hasColumn('client_tc_matches', 'flags')) {
                $table->dropColumn('flags');
            }
        });
    }
};
