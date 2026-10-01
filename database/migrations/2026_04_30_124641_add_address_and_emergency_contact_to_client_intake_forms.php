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
            $table->string('address')->nullable()->after('phone');
            $table->string('emergency_contact_name')->nullable()->after('postcode');
            $table->string('emergency_contact_phone')->nullable()->after('emergency_contact_name');
            $table->string('emergency_contact_relationship')->nullable()->after('emergency_contact_phone');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('client_intake_forms', function (Blueprint $table) {
            $table->dropColumn(['address', 'emergency_contact_name', 'emergency_contact_phone', 'emergency_contact_relationship']);
        });
    }
};
