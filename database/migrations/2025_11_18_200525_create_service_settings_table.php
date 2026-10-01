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
        Schema::create('service_settings', function (Blueprint $table) {
            $table->id();
            $table->string('service_name')->unique();
            $table->boolean('capacity_full')->default(false);
            $table->text('capacity_message')->nullable();
            $table->string('alternative_url')->nullable();
            $table->timestamps();
        });

        // Insert default settings for Ish's service
        DB::table('service_settings')->insert([
            'service_name' => 'Ish',
            'capacity_full' => false,
            'capacity_message' => 'This service is at capacity at this time. If you would like to work with Ish, you can proceed with our Partner service VQT COACHING & THERAPY.',
            'alternative_url' => 'https://pci.jotform.com/form/243161740962456',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('service_settings');
    }
};

