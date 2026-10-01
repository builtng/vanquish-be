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
        Schema::table('messages', function (Blueprint $table) {
            if (!Schema::hasColumn('messages', 'cc_users')) {
                $table->json('cc_users')->nullable()->after('message');
            }
            // For softDeletes, it's also good to guard
            if (!Schema::hasColumn('messages', 'deleted_at')) {
                $table->softDeletes();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            if (Schema::hasColumn('messages', 'cc_users')) {
                $table->dropColumn('cc_users');
            }
            if (Schema::hasColumn('messages', 'deleted_at')) {
                $table->dropSoftDeletes();
            }
        });
    }
};
