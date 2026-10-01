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
        Schema::table('client_intake_forms', function (Blueprint $table) {
            $table->string('whatsapp_agreement')->nullable();
            $table->string('partner_email')->nullable();
            $table->string('partner_phone')->nullable();
            $table->text('working_with_another_reason')->nullable();
            $table->string('location_of_residence')->nullable();
            $table->string('referral_type')->nullable();
        });

        Schema::table('clients', function (Blueprint $table) {
            $table->string('whatsapp_agreement')->nullable();
            $table->string('partner_email')->nullable();
            $table->string('partner_phone')->nullable();
            $table->text('working_with_another_reason')->nullable();
            $table->string('location_of_residence')->nullable();
            $table->string('referral_type')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('client_intake_forms', function (Blueprint $table) {
            $table->dropColumn([
                'whatsapp_agreement',
                'partner_email',
                'partner_phone',
                'working_with_another_reason',
                'location_of_residence',
                'referral_type'
            ]);
        });

        Schema::table('clients', function (Blueprint $table) {
            $table->dropColumn([
                'whatsapp_agreement',
                'partner_email',
                'partner_phone',
                'working_with_another_reason',
                'location_of_residence',
                'referral_type'
            ]);
        });
    }
};
