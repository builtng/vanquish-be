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
        Schema::table('clients', function (Blueprint $table) {
            $table->enum('agreement_status', ['not_sent', 'sent', 'signed'])->default('not_sent')->after('stage');
            $table->timestamp('agreement_sent_at')->nullable()->after('agreement_status');
            $table->timestamp('agreement_signed_at')->nullable()->after('agreement_sent_at');
            $table->string('agreement_jotform_id')->nullable()->after('agreement_signed_at');
            
            // Emergency contact fields
            $table->string('emergency_contact_name')->nullable()->after('agreement_jotform_id');
            $table->string('emergency_contact_phone')->nullable()->after('emergency_contact_name');
            $table->string('emergency_contact_relationship')->nullable()->after('emergency_contact_phone');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->dropColumn([
                'agreement_status',
                'agreement_sent_at',
                'agreement_signed_at',
                'agreement_jotform_id',
                'emergency_contact_name',
                'emergency_contact_phone',
                'emergency_contact_relationship',
            ]);
        });
    }
};

