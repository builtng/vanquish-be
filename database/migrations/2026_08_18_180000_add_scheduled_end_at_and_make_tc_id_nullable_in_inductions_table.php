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
        Schema::table('inductions', function (Blueprint $table) {
            if (!Schema::hasColumn('inductions', 'scheduled_end_at')) {
                $table->dateTime('scheduled_end_at')->nullable()->after('scheduled_at');
            }
            $table->unsignedBigInteger('tc_id')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('inductions', function (Blueprint $table) {
            if (Schema::hasColumn('inductions', 'scheduled_end_at')) {
                $table->dropColumn('scheduled_end_at');
            }
            $table->unsignedBigInteger('tc_id')->nullable(false)->change();
        });
    }
};
