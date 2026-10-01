<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Widen VARCHAR(255) columns to TEXT across all tables where the stored
 * data can exceed 255 characters:
 *
 * - URL / file-path columns:    storage URLs can be arbitrarily long
 * - address columns:            full street addresses often exceed 255 chars
 * - attachment_path:            file system paths can be long
 * - video_url / zoom_link:      Zoom / video URLs can be very long
 * - jotform_url:                JotForm embed URLs can be very long
 * - supervisor_link:            external meeting links can be very long
 * - messages.attachment_path:   storage paths
 * - college_address:            full postal address
 */
return new class extends Migration
{
    public function up(): void
    {
        // ── trainee_applications ──────────────────────────────────────────────
        Schema::table('trainee_applications', function (Blueprint $table) {
            if (Schema::hasColumn('trainee_applications', 'college_address')) {
                $table->text('college_address')->nullable()->change();
            }
            if (Schema::hasColumn('trainee_applications', 'video_url')) {
                $table->text('video_url')->nullable()->change();
            }
            if (Schema::hasColumn('trainee_applications', 'zoom_link')) {
                $table->text('zoom_link')->nullable()->change();
            }
        });

        // ── training_counsellors ──────────────────────────────────────────────
        Schema::table('training_counsellors', function (Blueprint $table) {
            if (Schema::hasColumn('training_counsellors', 'registered_address')) {
                $table->text('registered_address')->nullable()->change();
            }
            if (Schema::hasColumn('training_counsellors', 'self_employment_proof')) {
                $table->text('self_employment_proof')->nullable()->change();
            }
            if (Schema::hasColumn('training_counsellors', 'professional_membership')) {
                $table->text('professional_membership')->nullable()->change();
            }
            if (Schema::hasColumn('training_counsellors', 'zoom_link')) {
                $table->text('zoom_link')->nullable()->change();
            }
        });

        // ── client_intake_forms ───────────────────────────────────────────────
        Schema::table('client_intake_forms', function (Blueprint $table) {
            if (Schema::hasColumn('client_intake_forms', 'address')) {
                $table->text('address')->nullable()->change();
            }
        });

        // ── messages ─────────────────────────────────────────────────────────
        Schema::table('messages', function (Blueprint $table) {
            if (Schema::hasColumn('messages', 'attachment_path')) {
                $table->text('attachment_path')->nullable()->change();
            }
        });

        // ── attendance_groups ─────────────────────────────────────────────────
        Schema::table('attendance_groups', function (Blueprint $table) {
            if (Schema::hasColumn('attendance_groups', 'jotform_url')) {
                $table->text('jotform_url')->nullable()->change();
            }
            if (Schema::hasColumn('attendance_groups', 'supervisor_link')) {
                $table->text('supervisor_link')->nullable()->change();
            }
        });

        // ── consultation_bookings ─────────────────────────────────────────────
        if (Schema::hasTable('consultation_bookings')) {
            Schema::table('consultation_bookings', function (Blueprint $table) {
                if (Schema::hasColumn('consultation_bookings', 'zoom_link')) {
                    $table->text('zoom_link')->nullable()->change();
                }
            });
        }

        // ── consultation_slots ────────────────────────────────────────────────
        Schema::table('consultation_slots', function (Blueprint $table) {
            if (Schema::hasColumn('consultation_slots', 'zoom_link')) {
                $table->text('zoom_link')->nullable()->change();
            }
        });

        // ── service_settings ─────────────────────────────────────────────────
        Schema::table('service_settings', function (Blueprint $table) {
            if (Schema::hasColumn('service_settings', 'alternative_url')) {
                $table->text('alternative_url')->nullable()->change();
            }
        });
    }

    public function down(): void
    {
        Schema::table('trainee_applications', function (Blueprint $table) {
            if (Schema::hasColumn('trainee_applications', 'college_address')) {
                $table->string('college_address')->nullable()->change();
            }
            if (Schema::hasColumn('trainee_applications', 'video_url')) {
                $table->string('video_url')->nullable()->change();
            }
            if (Schema::hasColumn('trainee_applications', 'zoom_link')) {
                $table->string('zoom_link')->nullable()->change();
            }
        });

        Schema::table('training_counsellors', function (Blueprint $table) {
            if (Schema::hasColumn('training_counsellors', 'registered_address')) {
                $table->string('registered_address')->nullable()->change();
            }
            if (Schema::hasColumn('training_counsellors', 'self_employment_proof')) {
                $table->string('self_employment_proof')->nullable()->change();
            }
            if (Schema::hasColumn('training_counsellors', 'professional_membership')) {
                $table->string('professional_membership')->nullable()->change();
            }
            if (Schema::hasColumn('training_counsellors', 'zoom_link')) {
                $table->string('zoom_link')->nullable()->change();
            }
        });

        Schema::table('client_intake_forms', function (Blueprint $table) {
            if (Schema::hasColumn('client_intake_forms', 'address')) {
                $table->string('address')->nullable()->change();
            }
        });

        Schema::table('messages', function (Blueprint $table) {
            if (Schema::hasColumn('messages', 'attachment_path')) {
                $table->string('attachment_path')->nullable()->change();
            }
        });

        Schema::table('attendance_groups', function (Blueprint $table) {
            if (Schema::hasColumn('attendance_groups', 'jotform_url')) {
                $table->string('jotform_url')->nullable()->change();
            }
            if (Schema::hasColumn('attendance_groups', 'supervisor_link')) {
                $table->string('supervisor_link')->nullable()->change();
            }
        });

        if (Schema::hasTable('consultation_bookings')) {
            Schema::table('consultation_bookings', function (Blueprint $table) {
                if (Schema::hasColumn('consultation_bookings', 'zoom_link')) {
                    $table->string('zoom_link')->nullable()->change();
                }
            });
        }

        Schema::table('consultation_slots', function (Blueprint $table) {
            if (Schema::hasColumn('consultation_slots', 'zoom_link')) {
                $table->string('zoom_link')->nullable()->change();
            }
        });

        Schema::table('service_settings', function (Blueprint $table) {
            if (Schema::hasColumn('service_settings', 'alternative_url')) {
                $table->string('alternative_url')->nullable()->change();
            }
        });
    }
};
