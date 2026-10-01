<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attendance_groups', function (Blueprint $table) {
            $table->string('supervisor_name')->nullable()->after('supervisor_link');
            $table->string('supervisor_email')->nullable()->after('supervisor_name');
        });
    }

    public function down(): void
    {
        Schema::table('attendance_groups', function (Blueprint $table) {
            $table->dropColumn(['supervisor_name', 'supervisor_email']);
        });
    }
};
