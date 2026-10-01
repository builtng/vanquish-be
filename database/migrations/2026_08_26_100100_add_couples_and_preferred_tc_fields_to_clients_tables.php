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
            $table->boolean('is_couples')->default(false)->after('service_type');
            $table->string('partner_first_name')->nullable()->after('partner_phone');
            $table->string('partner_last_name')->nullable()->after('partner_first_name');
            $table->integer('partner_age')->nullable()->after('partner_last_name');
            $table->string('partner_gender')->nullable()->after('partner_age');
            $table->string('partner_ethnicity')->nullable()->after('partner_gender');
            $table->string('partner_sexual_orientation')->nullable()->after('partner_ethnicity');
            // Plain nullable column, no DB-level FK constraint: adding a
            // foreign key here would force SQLite (the local dev driver) to
            // rebuild the whole `clients` table via its copy-and-rename
            // emulation, which is unnecessary risk for a soft preference
            // field. The Eloquent relation (Client::preferredTc()) enforces
            // the association at the application level instead.
            $table->unsignedBigInteger('preferred_tc_id')->nullable()->after('matched_tc_id');
        });

        Schema::table('client_intake_forms', function (Blueprint $table) {
            $table->boolean('is_couples')->default(false)->after('service_type');
            $table->string('partner_first_name')->nullable()->after('partner_phone');
            $table->string('partner_last_name')->nullable()->after('partner_first_name');
            $table->integer('partner_age')->nullable()->after('partner_last_name');
            $table->string('partner_gender')->nullable()->after('partner_age');
            $table->string('partner_ethnicity')->nullable()->after('partner_gender');
            $table->string('partner_sexual_orientation')->nullable()->after('partner_ethnicity');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->dropColumn([
                'is_couples',
                'partner_first_name',
                'partner_last_name',
                'partner_age',
                'partner_gender',
                'partner_ethnicity',
                'partner_sexual_orientation',
                'preferred_tc_id',
            ]);
        });

        Schema::table('client_intake_forms', function (Blueprint $table) {
            $table->dropColumn([
                'is_couples',
                'partner_first_name',
                'partner_last_name',
                'partner_age',
                'partner_gender',
                'partner_ethnicity',
                'partner_sexual_orientation',
            ]);
        });
    }
};
