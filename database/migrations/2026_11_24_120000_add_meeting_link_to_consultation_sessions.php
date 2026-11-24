<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A video-call link per booked therapy session, added by an admin or the
 * session's counsellor. The portal's "Join Session" button opens it.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('consultation_sessions', 'meeting_link')) {
            Schema::table('consultation_sessions', function (Blueprint $table) {
                $table->string('meeting_link', 500)->nullable()->after('notes');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('consultation_sessions', 'meeting_link')) {
            Schema::table('consultation_sessions', function (Blueprint $table) {
                $table->dropColumn('meeting_link');
            });
        }
    }
};
