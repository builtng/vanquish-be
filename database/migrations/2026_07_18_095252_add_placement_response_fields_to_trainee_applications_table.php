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
            $table->string('placement_response_token')->nullable()->unique()->after('induction_date');
            $table->boolean('placement_accepted')->nullable()->after('placement_response_token');
            $table->boolean('induction_rsvp')->nullable()->after('placement_accepted');
            $table->timestamp('placement_responded_at')->nullable()->after('induction_rsvp');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('trainee_applications', function (Blueprint $table) {
            $table->dropColumn(['placement_response_token', 'placement_accepted', 'induction_rsvp', 'placement_responded_at']);
        });
    }
};
