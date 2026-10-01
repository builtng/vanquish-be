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
        Schema::table('attendance_groups', function (Blueprint $table) {
            $table->renameColumn('jotform_url', 'supervisor_link');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('attendance_groups', function (Blueprint $table) {
            $table->renameColumn('supervisor_link', 'jotform_url');
        });
    }
};
