<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The acceptance email now carries an "Accept" link that is valid for 72
 * hours. Record when it was sent, and let qualified counsellor applications
 * record their response the same way trainee applications do.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('trainee_applications', function (Blueprint $table) {
            if (!Schema::hasColumn('trainee_applications', 'placement_response_sent_at')) {
                $table->timestamp('placement_response_sent_at')->nullable();
            }
        });

        Schema::table('qc_applications', function (Blueprint $table) {
            if (!Schema::hasColumn('qc_applications', 'placement_response_sent_at')) {
                $table->timestamp('placement_response_sent_at')->nullable();
            }
            if (!Schema::hasColumn('qc_applications', 'placement_accepted')) {
                $table->boolean('placement_accepted')->nullable();
            }
            if (!Schema::hasColumn('qc_applications', 'induction_rsvp')) {
                $table->boolean('induction_rsvp')->nullable();
            }
            if (!Schema::hasColumn('qc_applications', 'placement_responded_at')) {
                $table->timestamp('placement_responded_at')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('trainee_applications', function (Blueprint $table) {
            if (Schema::hasColumn('trainee_applications', 'placement_response_sent_at')) {
                $table->dropColumn('placement_response_sent_at');
            }
        });
        Schema::table('qc_applications', function (Blueprint $table) {
            foreach (['placement_response_sent_at', 'placement_accepted', 'induction_rsvp', 'placement_responded_at'] as $col) {
                if (Schema::hasColumn('qc_applications', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
