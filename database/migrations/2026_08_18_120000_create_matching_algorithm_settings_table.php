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
        Schema::create('matching_algorithm_settings', function (Blueprint $table) {
            $table->id();
            $table->decimal('availability_weight', 5, 2)->default(40);
            $table->decimal('modality_weight', 5, 2)->default(20);
            $table->decimal('gender_weight', 5, 2)->default(20);
            $table->decimal('ethnicity_weight', 5, 2)->default(10);
            $table->decimal('sexual_orientation_weight', 5, 2)->default(5);
            $table->decimal('age_weight', 5, 2)->default(5);
            $table->timestamps();
        });

        // Seed the single settings row with the proposed defaults
        DB::table('matching_algorithm_settings')->insert([
            'availability_weight'       => 40,
            'modality_weight'           => 20,
            'gender_weight'             => 20,
            'ethnicity_weight'          => 10,
            'sexual_orientation_weight' => 5,
            'age_weight'                => 5,
            'created_at'                => now(),
            'updated_at'                => now(),
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('matching_algorithm_settings');
    }
};
