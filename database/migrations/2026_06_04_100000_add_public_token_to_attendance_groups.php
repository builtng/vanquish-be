<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attendance_groups', function (Blueprint $table) {
            $table->string('public_token', 64)->nullable()->unique()->after('day_of_week');
        });

        // Generate tokens for existing groups
        DB::table('attendance_groups')->whereNull('public_token')->get()->each(function ($group) {
            DB::table('attendance_groups')
                ->where('id', $group->id)
                ->update(['public_token' => Str::uuid()->toString()]);
        });
    }

    public function down(): void
    {
        Schema::table('attendance_groups', function (Blueprint $table) {
            $table->dropColumn('public_token');
        });
    }
};
