<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Adds all fields captured by the Stage 1 JotForm (230152076043040):
     * - Extended personal info (DOB, gender, ethnicity, sexual orientation, beliefs,
     *   disabilities, medical conditions)
     * - Fitness to Practise fields (insurance, professional body, DBS update service,
     *   personal therapy, clinical supervisor, previous online counselling, criminal record)
     * - Extended course info (tutor, placement lead, qualification date, counselling type,
     *   face-to-face requirements & clients, skills practice detail)
     * - Experience narrative fields (family/background, personal journey, self-awareness,
     *   training history, areas of experience, personal development, theoretical approach)
     * - Peer Support Group preference
     * - Document URLs (fitness to practise, prior qualifications, DBS, CV, ID, insurance)
     * - JotForm submission tracking (submission_id, submitted_at)
     * - Weekly availability schedule
     */
    public function up(): void
    {
        Schema::table('trainee_applications', function (Blueprint $table) {
            // ── Personal Information (extended) ───────────────────────────────
            $table->date('date_of_birth')->nullable()->after('last_name');
            $table->string('gender')->nullable()->after('date_of_birth');
            $table->string('ethnicity')->nullable()->after('gender');
            $table->string('sexual_orientation')->nullable()->after('ethnicity');
            $table->string('beliefs')->nullable()->after('sexual_orientation');
            $table->string('beliefs_other')->nullable()->after('beliefs');
            $table->text('disabilities')->nullable()->after('beliefs_other');
            $table->text('medical_conditions')->nullable()->after('disabilities');

            // ── Fitness to Practise ───────────────────────────────────────────
            $table->string('has_insurance')->nullable()->after('medical_conditions');          // Yes / No
            $table->string('professional_body_member')->nullable()->after('has_insurance');   // Yes / No + details
            $table->text('professional_body_details')->nullable()->after('professional_body_member');
            $table->string('dbs_update_service')->nullable()->after('professional_body_details'); // Yes / No + details
            $table->text('dbs_update_details')->nullable()->after('dbs_update_service');
            $table->string('in_personal_therapy')->nullable()->after('dbs_update_details'); // Yes / No
            $table->text('personal_therapy_reason')->nullable()->after('in_personal_therapy'); // reason if No
            $table->string('has_clinical_supervisor')->nullable()->after('personal_therapy_reason'); // Yes / No
            $table->text('supervisor_reason')->nullable()->after('has_clinical_supervisor'); // reason if No
            $table->string('previous_online_counselling')->nullable()->after('supervisor_reason'); // Yes(ind)/Yes(couples)/No
            $table->string('criminal_convictions')->nullable()->after('previous_online_counselling');

            // ── Weekly Availability ───────────────────────────────────────────
            $table->text('availability_schedule')->nullable()->after('criminal_convictions');

            // ── Course Information (extended) ─────────────────────────────────
            $table->string('college_address')->nullable()->after('institution');
            $table->string('tutor_name')->nullable()->after('college_address');
            $table->string('tutor_email')->nullable()->after('tutor_name');
            $table->string('tutor_phone')->nullable()->after('tutor_email');
            $table->string('placement_lead_name')->nullable()->after('tutor_phone');
            $table->string('placement_lead_phone')->nullable()->after('placement_lead_name');
            $table->string('placement_lead_email')->nullable()->after('placement_lead_phone');
            $table->string('course_title')->nullable()->after('placement_lead_email');
            $table->string('expected_qualification_date')->nullable()->after('course_title');
            $table->string('counselling_type')->nullable()->after('expected_qualification_date'); // individual/couples/both
            $table->text('face_to_face_requirement')->nullable()->after('counselling_type');
            $table->string('has_face_to_face_clients')->nullable()->after('face_to_face_requirement');
            $table->string('face_to_face_client_count')->nullable()->after('has_face_to_face_clients');
            $table->string('face_to_face_hours_completed')->nullable()->after('face_to_face_client_count');
            $table->text('skills_practice_details')->nullable()->after('face_to_face_hours_completed');

            // ── Experience / Journey Questions ────────────────────────────────
            $table->text('family_impact')->nullable()->after('skills_practice_details');
            $table->text('personal_journey')->nullable()->after('family_impact');
            $table->text('self_awareness')->nullable()->after('personal_journey');
            $table->text('training_history')->nullable()->after('self_awareness');
            $table->text('areas_of_experience')->nullable()->after('training_history');
            $table->text('personal_development_areas')->nullable()->after('areas_of_experience');
            $table->string('theoretical_approach')->nullable()->after('personal_development_areas');

            // ── Peer Support Group ────────────────────────────────────────────
            $table->string('psg_day_preference')->nullable()->after('theoretical_approach');
            $table->text('psg_reason')->nullable()->after('psg_day_preference');

            // ── Document Upload URLs ──────────────────────────────────────────
            $table->string('doc_fitness_to_practise')->nullable()->after('psg_reason');
            $table->string('doc_prior_qualifications')->nullable()->after('doc_fitness_to_practise');
            $table->string('doc_dbs_certificate')->nullable()->after('doc_prior_qualifications');
            $table->string('doc_cv')->nullable()->after('doc_dbs_certificate');
            $table->string('doc_valid_id')->nullable()->after('doc_cv');
            $table->string('doc_indemnity_insurance')->nullable()->after('doc_valid_id');

            // ── JotForm Submission Tracking ───────────────────────────────────
            $table->string('jotform_submission_id')->nullable()->after('source');
            $table->timestamp('jotform_submitted_at')->nullable()->after('jotform_submission_id');
            $table->json('jotform_raw_data')->nullable()->after('jotform_submitted_at');

            // ── Disclaimer ───────────────────────────────────────────────────
            $table->date('disclaimer_date')->nullable()->after('jotform_raw_data');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('trainee_applications', function (Blueprint $table) {
            $table->dropColumn([
                'date_of_birth', 'gender', 'ethnicity', 'sexual_orientation',
                'beliefs', 'beliefs_other', 'disabilities', 'medical_conditions',
                'has_insurance', 'professional_body_member', 'professional_body_details',
                'dbs_update_service', 'dbs_update_details',
                'in_personal_therapy', 'personal_therapy_reason',
                'has_clinical_supervisor', 'supervisor_reason',
                'previous_online_counselling', 'criminal_convictions',
                'availability_schedule',
                'college_address', 'tutor_name', 'tutor_email', 'tutor_phone',
                'placement_lead_name', 'placement_lead_phone', 'placement_lead_email',
                'course_title', 'expected_qualification_date', 'counselling_type',
                'face_to_face_requirement', 'has_face_to_face_clients',
                'face_to_face_client_count', 'face_to_face_hours_completed',
                'skills_practice_details',
                'family_impact', 'personal_journey', 'self_awareness',
                'training_history', 'areas_of_experience', 'personal_development_areas',
                'theoretical_approach',
                'psg_day_preference', 'psg_reason',
                'doc_fitness_to_practise', 'doc_prior_qualifications',
                'doc_dbs_certificate', 'doc_cv', 'doc_valid_id', 'doc_indemnity_insurance',
                'jotform_submission_id', 'jotform_submitted_at', 'jotform_raw_data',
                'disclaimer_date',
            ]);
        });
    }
};
