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
            if (!Schema::hasColumn('clients', 'core34_answers')) {
                $table->json('core34_answers')->nullable()->after('referral_type');
            }
        });

        Schema::table('client_intake_forms', function (Blueprint $table) {
            if (!Schema::hasColumn('client_intake_forms', 'core34_answers')) {
                $table->json('core34_answers')->nullable()->after('referral_type');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->dropColumn('core34_answers');
        });

        Schema::table('client_intake_forms', function (Blueprint $table) {
            $table->dropColumn('core34_answers');
        });
    }
};
