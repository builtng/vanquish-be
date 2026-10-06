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
        // 1. activity_logs: add hidden_at and hidden_by for note-hiding audit retention
        Schema::table('activity_logs', function (Blueprint $table) {
            if (!Schema::hasColumn('activity_logs', 'hidden_at')) {
                $table->timestamp('hidden_at')->nullable()->after('changes');
            }
            if (!Schema::hasColumn('activity_logs', 'hidden_by')) {
                $table->unsignedBigInteger('hidden_by')->nullable()->after('hidden_at');
            }
        });

        // 2. consultations: add cancelled_at, cancelled_by, archived_at, deleted_at
        Schema::table('consultations', function (Blueprint $table) {
            if (!Schema::hasColumn('consultations', 'cancelled_at')) {
                $table->timestamp('cancelled_at')->nullable()->after('status');
            }
            if (!Schema::hasColumn('consultations', 'cancelled_by')) {
                $table->unsignedBigInteger('cancelled_by')->nullable()->after('cancelled_at');
            }
            if (!Schema::hasColumn('consultations', 'archived_at')) {
                $table->timestamp('archived_at')->nullable()->after('cancelled_by');
            }
            if (!Schema::hasColumn('consultations', 'deleted_at')) {
                $table->softDeletes()->after('archived_at');
            }
        });

        // 3. consultation_sessions: add cancelled_at, cancelled_by, archived_at, deleted_at
        Schema::table('consultation_sessions', function (Blueprint $table) {
            if (!Schema::hasColumn('consultation_sessions', 'cancelled_at')) {
                $table->timestamp('cancelled_at')->nullable()->after('status');
            }
            if (!Schema::hasColumn('consultation_sessions', 'cancelled_by')) {
                $table->unsignedBigInteger('cancelled_by')->nullable()->after('cancelled_at');
            }
            if (!Schema::hasColumn('consultation_sessions', 'archived_at')) {
                $table->timestamp('archived_at')->nullable()->after('cancelled_by');
            }
            if (!Schema::hasColumn('consultation_sessions', 'deleted_at')) {
                $table->softDeletes()->after('archived_at');
            }
        });

        // 4. qc_applications: add deleted_at for SoftDeletes
        Schema::table('qc_applications', function (Blueprint $table) {
            if (!Schema::hasColumn('qc_applications', 'deleted_at')) {
                $table->softDeletes()->after('archived_at');
            }
        });

        // 5. messages: add per-user soft delete columns
        Schema::table('messages', function (Blueprint $table) {
            if (!Schema::hasColumn('messages', 'deleted_by_sender_at')) {
                $table->timestamp('deleted_by_sender_at')->nullable()->after('is_trashed');
            }
            if (!Schema::hasColumn('messages', 'deleted_by_recipient_at')) {
                $table->timestamp('deleted_by_recipient_at')->nullable()->after('deleted_by_sender_at');
            }
            if (!Schema::hasColumn('messages', 'deleted_at')) {
                $table->softDeletes()->after('deleted_by_recipient_at');
            }
        });

        // 6. session_notes: add archived_at and deleted_at
        Schema::table('session_notes', function (Blueprint $table) {
            if (!Schema::hasColumn('session_notes', 'archived_at')) {
                $table->timestamp('archived_at')->nullable()->after('status');
            }
            if (!Schema::hasColumn('session_notes', 'deleted_at')) {
                $table->softDeletes()->after('archived_at');
            }
        });

        // 7. staff_notes: add archived_at and deleted_at
        Schema::table('staff_notes', function (Blueprint $table) {
            if (!Schema::hasColumn('staff_notes', 'archived_at')) {
                $table->timestamp('archived_at')->nullable()->after('is_read');
            }
            if (!Schema::hasColumn('staff_notes', 'deleted_at')) {
                $table->softDeletes()->after('archived_at');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('activity_logs', function (Blueprint $table) {
            if (Schema::hasColumn('activity_logs', 'hidden_by')) {
                $table->dropColumn('hidden_by');
            }
            if (Schema::hasColumn('activity_logs', 'hidden_at')) {
                $table->dropColumn('hidden_at');
            }
        });

        Schema::table('consultations', function (Blueprint $table) {
            if (Schema::hasColumn('consultations', 'deleted_at')) {
                $table->dropSoftDeletes();
            }
            if (Schema::hasColumn('consultations', 'archived_at')) {
                $table->dropColumn('archived_at');
            }
            if (Schema::hasColumn('consultations', 'cancelled_by')) {
                $table->dropColumn('cancelled_by');
            }
            if (Schema::hasColumn('consultations', 'cancelled_at')) {
                $table->dropColumn('cancelled_at');
            }
        });

        Schema::table('consultation_sessions', function (Blueprint $table) {
            if (Schema::hasColumn('consultation_sessions', 'deleted_at')) {
                $table->dropSoftDeletes();
            }
            if (Schema::hasColumn('consultation_sessions', 'archived_at')) {
                $table->dropColumn('archived_at');
            }
            if (Schema::hasColumn('consultation_sessions', 'cancelled_by')) {
                $table->dropColumn('cancelled_by');
            }
            if (Schema::hasColumn('consultation_sessions', 'cancelled_at')) {
                $table->dropColumn('cancelled_at');
            }
        });

        Schema::table('qc_applications', function (Blueprint $table) {
            if (Schema::hasColumn('qc_applications', 'deleted_at')) {
                $table->dropSoftDeletes();
            }
        });

        Schema::table('messages', function (Blueprint $table) {
            if (Schema::hasColumn('messages', 'deleted_by_recipient_at')) {
                $table->dropColumn('deleted_by_recipient_at');
            }
            if (Schema::hasColumn('messages', 'deleted_by_sender_at')) {
                $table->dropColumn('deleted_by_sender_at');
            }
        });

        Schema::table('session_notes', function (Blueprint $table) {
            if (Schema::hasColumn('session_notes', 'deleted_at')) {
                $table->dropSoftDeletes();
            }
            if (Schema::hasColumn('session_notes', 'archived_at')) {
                $table->dropColumn('archived_at');
            }
        });

        Schema::table('staff_notes', function (Blueprint $table) {
            if (Schema::hasColumn('staff_notes', 'deleted_at')) {
                $table->dropSoftDeletes();
            }
            if (Schema::hasColumn('staff_notes', 'archived_at')) {
                $table->dropColumn('archived_at');
            }
        });
    }
};
