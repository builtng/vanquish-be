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
        Schema::table('training_counsellors', function (Blueprint $table) {
            $table->string('tutor_name')->nullable()->after('institution');
            $table->string('tutor_email')->nullable()->after('tutor_name');
            $table->string('tutor_phone')->nullable()->after('tutor_email');
            $table->string('placement_lead_name')->nullable()->after('tutor_phone');
            $table->string('placement_lead_email')->nullable()->after('placement_lead_name');
            $table->string('placement_lead_phone')->nullable()->after('placement_lead_email');
            $table->text('training_org_address')->nullable()->after('institution');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('training_counsellors', function (Blueprint $table) {
            $table->dropColumn([
                'tutor_name', 
                'tutor_email', 
                'tutor_phone',
                'placement_lead_name',
                'placement_lead_email',
                'placement_lead_phone',
                'training_org_address'
            ]);
        });
    }
};
