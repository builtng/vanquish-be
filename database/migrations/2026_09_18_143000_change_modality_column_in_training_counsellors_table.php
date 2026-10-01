<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (DB::getDriverName() !== 'sqlite') {
            // Modify modality column from restrictive ENUM to TEXT
            DB::statement("ALTER TABLE training_counsellors MODIFY COLUMN modality TEXT NULL");
        } else {
            Schema::table('training_counsellors', function (Blueprint $table) {
                $table->text('modality')->nullable()->change();
            });
        }

        // Also ensure document path columns have enough space
        Schema::table('training_counsellors', function (Blueprint $table) {
            if (Schema::hasColumn('training_counsellors', 'qualification_document')) {
                $table->text('qualification_document')->nullable()->change();
            }
            if (Schema::hasColumn('training_counsellors', 'dbs_certificate_qualified')) {
                $table->text('dbs_certificate_qualified')->nullable()->change();
            }
            if (Schema::hasColumn('training_counsellors', 'insurance_qualified')) {
                $table->text('insurance_qualified')->nullable()->change();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::getDriverName() !== 'sqlite') {
            DB::statement("ALTER TABLE training_counsellors MODIFY COLUMN modality ENUM('CBT', 'Person-Centred', 'Integrative', 'Psychodynamic', 'Other') NULL");
        } else {
            Schema::table('training_counsellors', function (Blueprint $table) {
                $table->string('modality', 255)->nullable()->change();
            });
        }
    }
};
