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
            $table->decimal('session_price', 10, 2)->nullable()->after('modality');
            $table->text('bio')->nullable()->after('session_price');
            $table->boolean('offers_mid_range')->default(false)->after('bio');
            $table->boolean('offers_coaching')->default(false)->after('offers_mid_range');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('training_counsellors', function (Blueprint $table) {
            $table->dropColumn(['session_price', 'bio', 'offers_mid_range', 'offers_coaching']);
        });
    }
};
