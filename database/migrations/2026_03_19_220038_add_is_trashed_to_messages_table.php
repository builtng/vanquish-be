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
            if (!Schema::hasColumn('messages', 'is_trashed')) {
                $table->boolean('is_trashed')->default(false);
            }
            if (!Schema::hasColumn('messages', 'trashed_at')) {
                $table->timestamp('trashed_at')->nullable();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            if (Schema::hasColumn('messages', 'is_trashed')) {
                $table->dropColumn('is_trashed');
            }
            if (Schema::hasColumn('messages', 'trashed_at')) {
                $table->dropColumn('trashed_at');
            }
        });
    }
};
