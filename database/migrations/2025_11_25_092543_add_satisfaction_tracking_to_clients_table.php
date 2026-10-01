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
        Schema::table('clients', function (Blueprint $table) {
            $table->date('last_feedback_sent_at')->nullable()->after('matched_date');
            $table->date('last_feedback_date')->nullable()->after('last_feedback_sent_at');
            $table->decimal('satisfaction_score', 3, 2)->nullable()->after('last_feedback_date')->comment('Average satisfaction score (0-5)');
            $table->integer('feedback_count')->default(0)->after('satisfaction_score');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->dropColumn(['last_feedback_sent_at', 'last_feedback_date', 'satisfaction_score', 'feedback_count']);
        });
    }
};
