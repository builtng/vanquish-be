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
        Schema::table('client_intake_forms', function (Blueprint $table) {
            $table->string('payment_status')->nullable()->after('status');
            $table->string('payment_reference')->nullable()->after('payment_status');
            $table->decimal('payment_amount', 10, 2)->nullable()->after('payment_reference');
            $table->string('payment_method')->nullable()->after('payment_amount');
            $table->timestamp('paid_at')->nullable()->after('payment_method');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('client_intake_forms', function (Blueprint $table) {
            $table->dropColumn([
                'payment_status',
                'payment_reference',
                'payment_amount',
                'payment_method',
                'paid_at'
            ]);
        });
    }
};
