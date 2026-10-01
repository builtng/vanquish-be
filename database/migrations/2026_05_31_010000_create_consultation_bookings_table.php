<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('consultation_bookings', function (Blueprint $table) {
            $table->id();
            $table->string('booking_id')->unique();
            $table->foreignId('client_id')->nullable()->constrained('clients')->nullOnDelete();
            $table->foreignId('consultation_id')->nullable()->constrained('consultations')->nullOnDelete();
            $table->foreignId('consultation_slot_id')->nullable()->constrained('consultation_slots')->nullOnDelete();
            $table->string('client_name');
            $table->string('client_email');
            $table->string('client_phone')->nullable();
            $table->dateTime('scheduled_at')->nullable();
            $table->string('status')->default('pending_payment');
            $table->string('payment_status')->default('pending');
            $table->decimal('payment_amount', 8, 2)->nullable();
            $table->string('stripe_payment_intent_id')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->string('payment_method')->nullable();
            $table->string('zoom_link')->nullable();
            $table->string('meeting_id')->nullable();
            $table->string('passcode')->nullable();
            $table->text('reschedule_notes')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['status', 'scheduled_at']);
            $table->index('client_email');
        });

        Schema::table('clients', function (Blueprint $table) {
            if (!Schema::hasColumn('clients', 'is_consultation_booking_only')) {
                $table->boolean('is_consultation_booking_only')->default(false)->after('payment_status');
            }
        });
    }

    public function down(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            if (Schema::hasColumn('clients', 'is_consultation_booking_only')) {
                $table->dropColumn('is_consultation_booking_only');
            }
        });

        Schema::dropIfExists('consultation_bookings');
    }
};
