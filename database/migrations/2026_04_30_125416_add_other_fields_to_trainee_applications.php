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
        Schema::table('trainee_applications', function (Blueprint $table) {
            $table->string('ethnicity_other')->nullable()->after('ethnicity');
            $table->string('sexual_orientation_other')->nullable()->after('sexual_orientation');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('trainee_applications', function (Blueprint $table) {
            $table->dropColumn(['ethnicity_other', 'sexual_orientation_other']);
        });
    }
};
