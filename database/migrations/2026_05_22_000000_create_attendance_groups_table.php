<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('attendance_groups', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('jotform_url');
            $table->string('day_of_week');
            $table->timestamps();
        });

        Schema::table('training_counsellors', function (Blueprint $table) {
            $table->unsignedBigInteger('attendance_group_id')->nullable();
        });

        // Seed default groups
        DB::table('attendance_groups')->insert([
            [
                'name' => 'Group 5',
                'day_of_week' => 'Monday',
                'jotform_url' => 'https://form.jotform.com/252162390922454',
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'name' => 'Group 4',
                'day_of_week' => 'Thursday',
                'jotform_url' => 'https://form.jotform.com/250412125203438',
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'name' => 'Group 3',
                'day_of_week' => 'Wednesday',
                'jotform_url' => 'https://form.jotform.com/241365224856459',
                'created_at' => now(),
                'updated_at' => now()
            ]
        ]);

        // Seed menu privilege for admin dashboard sidebar link
        DB::table('menu_privileges')->insert([
            'menu_id' => 'psg-groups',
            'roles' => json_encode(['admin', 'super_admin', 'staff', 'consultation_staff', 'compliance_officer']),
            'created_at' => now(),
            'updated_at' => now()
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('menu_privileges')->where('menu_id', 'psg-groups')->delete();

        Schema::table('training_counsellors', function (Blueprint $table) {
            $table->dropColumn('attendance_group_id');
        });

        Schema::dropIfExists('attendance_groups');
    }
};
