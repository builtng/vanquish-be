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
            // Fix enum columns by converting them to string for better flexibility
            $table->string('stage')->default('Application & Assessment form Submitted')->change();
            $table->string('service_type')->nullable()->change();
            
            // Add missing personal info columns
            if (!Schema::hasColumn('clients', 'first_name')) {
                $table->string('first_name')->nullable()->after('name');
            }
            if (!Schema::hasColumn('clients', 'last_name')) {
                $table->string('last_name')->nullable()->after('first_name');
            }
            
            // Add missing clinical information columns
            if (!Schema::hasColumn('clients', 'on_medication')) {
                $table->string('on_medication')->nullable()->after('medication'); // Yes/No from frontend
            }
            if (!Schema::hasColumn('clients', 'medication_details')) {
                $table->text('medication_details')->nullable()->after('on_medication');
            }
            if (!Schema::hasColumn('clients', 'risk_issues')) {
                $table->text('risk_issues')->nullable()->after('risk_flags');
            }
            if (!Schema::hasColumn('clients', 'voicemail_permission')) {
                $table->string('voicemail_permission')->nullable()->after('disabilities');
            }
            if (!Schema::hasColumn('clients', 'currently_in_therapy')) {
                $table->string('currently_in_therapy')->nullable()->after('voicemail_permission');
            }
            
            // Add missing referral/admin columns
            if (!Schema::hasColumn('clients', 'how_heard_about')) {
                $table->string('how_heard_about')->nullable()->after('currently_in_therapy');
            }
            if (!Schema::hasColumn('clients', 'referral_reasons')) {
                $table->text('referral_reasons')->nullable()->after('how_heard_about');
            }
            if (!Schema::hasColumn('clients', 'referral_name')) {
                $table->string('referral_name')->nullable()->after('referral_reasons');
            }
            if (!Schema::hasColumn('clients', 'referral_phone')) {
                $table->string('referral_phone')->nullable()->after('referral_name');
            }
            if (!Schema::hasColumn('clients', 'organization_name')) {
                $table->string('organization_name')->nullable()->after('referral_phone');
            }
            if (!Schema::hasColumn('clients', 'organization_email')) {
                $table->string('organization_email')->nullable()->after('organization_name');
            }
            if (!Schema::hasColumn('clients', 'admin_notes')) {
                $table->text('admin_notes')->nullable()->after('organization_email');
            }
            if (!Schema::hasColumn('clients', 'payment_status')) {
                $table->string('payment_status')->nullable()->after('admin_notes');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->dropColumn([
                'first_name',
                'last_name',
                'on_medication',
                'medication_details',
                'risk_issues',
                'voicemail_permission',
                'currently_in_therapy',
                'how_heard_about',
                'referral_reasons',
                'referral_name',
                'referral_phone',
                'organization_name',
                'organization_email',
                'admin_notes',
                'payment_status',
            ]);
        });
    }
};
