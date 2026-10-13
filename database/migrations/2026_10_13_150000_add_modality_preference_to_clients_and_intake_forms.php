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
        if (Schema::hasTable('clients')) {
            Schema::table('clients', function (Blueprint $table) {
                if (!Schema::hasColumn('clients', 'modality_preference')) {
                    $table->string('modality_preference')->default('Unsure')->nullable()->after('orientation_preference');
                }
            });
        }

        if (Schema::hasTable('client_intake_forms')) {
            Schema::table('client_intake_forms', function (Blueprint $table) {
                if (!Schema::hasColumn('client_intake_forms', 'modality_preference')) {
                    $table->string('modality_preference')->default('Unsure')->nullable()->after('orientation_preference');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('clients')) {
            Schema::table('clients', function (Blueprint $table) {
                if (Schema::hasColumn('clients', 'modality_preference')) {
                    $table->dropColumn('modality_preference');
                }
            });
        }

        if (Schema::hasTable('client_intake_forms')) {
            Schema::table('client_intake_forms', function (Blueprint $table) {
                if (Schema::hasColumn('client_intake_forms', 'modality_preference')) {
                    $table->dropColumn('modality_preference');
                }
            });
        }
    }
};
