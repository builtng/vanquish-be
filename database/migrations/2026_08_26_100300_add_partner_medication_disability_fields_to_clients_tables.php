<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->boolean('partner_on_medication')->nullable()->after('partner_sexual_orientation');
            $table->text('partner_medication_details')->nullable()->after('partner_on_medication');
            $table->text('partner_disabilities')->nullable()->after('partner_medication_details');
        });

        Schema::table('client_intake_forms', function (Blueprint $table) {
            $table->boolean('partner_on_medication')->nullable()->after('partner_sexual_orientation');
            $table->text('partner_medication_details')->nullable()->after('partner_on_medication');
            $table->text('partner_disabilities')->nullable()->after('partner_medication_details');
        });
    }

    public function down(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->dropColumn(['partner_on_medication', 'partner_medication_details', 'partner_disabilities']);
        });

        Schema::table('client_intake_forms', function (Blueprint $table) {
            $table->dropColumn(['partner_on_medication', 'partner_medication_details', 'partner_disabilities']);
        });
    }
};
