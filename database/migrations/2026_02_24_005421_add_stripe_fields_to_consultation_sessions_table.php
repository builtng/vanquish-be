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
        Schema::table('consultation_sessions', function (Blueprint $table) {
            $table->string('stripe_payment_intent_id')->nullable()->after('payment_amount');
            $table->timestamp('paid_at')->nullable()->after('stripe_payment_intent_id');
            $table->string('payment_method')->nullable()->after('paid_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('consultation_sessions', function (Blueprint $table) {
            $table->dropColumn(['stripe_payment_intent_id', 'paid_at', 'payment_method']);
        });
    }
};
