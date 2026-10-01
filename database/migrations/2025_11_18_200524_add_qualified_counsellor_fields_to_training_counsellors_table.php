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
            // Add counsellor type to distinguish Trainee vs Qualified
            $table->enum('counsellor_type', ['Trainee', 'Qualified'])->default('Trainee')->after('status');

            // Qualified Counsellor specific fields
            $table->string('legal_first_name')->nullable()->after('name');
            $table->string('legal_last_name')->nullable()->after('legal_first_name');
            $table->string('registered_address')->nullable()->after('phone');
            $table->string('registered_city')->nullable()->after('registered_address');
            $table->string('registered_postcode')->nullable()->after('registered_city');
            $table->enum('has_supervisor', ['Yes', 'No'])->nullable()->after('registered_postcode');

            // Experience fields
            $table->text('previous_vanquish_work')->nullable()->after('has_supervisor');
            $table->text('areas_to_improve')->nullable()->after('previous_vanquish_work');
            $table->text('unique_trait')->nullable()->after('areas_to_improve');
            $table->text('counsellor_training_details')->nullable()->after('unique_trait');
            $table->json('qualified_to_work_with')->nullable()->after('counsellor_training_details'); // ['Individuals', 'Couples', 'Families']
            $table->text('challenging_cases')->nullable()->after('qualified_to_work_with');

            // Documents for qualified counsellors
            $table->string('qualification_document')->nullable()->after('challenging_cases');
            $table->string('dbs_certificate_qualified')->nullable()->after('qualification_document');
            $table->string('insurance_qualified')->nullable()->after('dbs_certificate_qualified');
            $table->string('self_employment_proof')->nullable()->after('insurance_qualified');
            $table->string('professional_membership')->nullable()->after('self_employment_proof');

            // Signature and date
            $table->text('signature')->nullable()->after('professional_membership');
            $table->date('signature_date')->nullable()->after('signature');

            // Track if qualified counsellor form is completed
            $table->boolean('qualified_form_completed')->default(false)->after('signature_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('training_counsellors', function (Blueprint $table) {
            $table->dropColumn([
                'counsellor_type',
                'legal_first_name',
                'legal_last_name',
                'registered_address',
                'registered_city',
                'registered_postcode',
                'has_supervisor',
                'previous_vanquish_work',
                'areas_to_improve',
                'unique_trait',
                'counsellor_training_details',
                'qualified_to_work_with',
                'challenging_cases',
                'qualification_document',
                'dbs_certificate_qualified',
                'insurance_qualified',
                'self_employment_proof',
                'professional_membership',
                'signature',
                'signature_date',
                'qualified_form_completed',
            ]);
        });
    }
};
