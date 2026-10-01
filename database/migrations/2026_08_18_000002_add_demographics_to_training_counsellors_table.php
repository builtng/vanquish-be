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
            if (!Schema::hasColumn('training_counsellors', 'gender')) {
                $table->string('gender')->nullable()->after('phone');
            }
            if (!Schema::hasColumn('training_counsellors', 'ethnicity')) {
                $table->string('ethnicity')->nullable()->after('gender');
            }
            if (!Schema::hasColumn('training_counsellors', 'sexual_orientation')) {
                $table->string('sexual_orientation')->nullable()->after('ethnicity');
            }
            if (!Schema::hasColumn('training_counsellors', 'age')) {
                $table->integer('age')->nullable()->after('sexual_orientation');
            }
            if (!Schema::hasColumn('training_counsellors', 'date_of_birth')) {
                $table->string('date_of_birth')->nullable()->after('age');
            }
            if (!Schema::hasColumn('training_counsellors', 'address')) {
                $table->text('address')->nullable()->after('date_of_birth');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('training_counsellors', function (Blueprint $table) {
            $columnsToDrop = [];
            foreach (['gender', 'ethnicity', 'sexual_orientation', 'age', 'date_of_birth', 'address'] as $col) {
                if (Schema::hasColumn('training_counsellors', $col)) {
                    $columnsToDrop[] = $col;
                }
            }
            if (!empty($columnsToDrop)) {
                $table->dropColumn($columnsToDrop);
            }
        });
    }
};
