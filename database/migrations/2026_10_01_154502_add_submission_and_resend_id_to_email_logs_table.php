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
        Schema::table('email_logs', function (Blueprint $table) {
            $table->unsignedBigInteger('submission_id')->nullable()->after('client_id');
            $table->string('resend_message_id')->nullable()->after('status');
            $table->index(['email', 'created_at']);
            $table->index('template_name');
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('email_logs', function (Blueprint $table) {
            $table->dropIndex(['email', 'created_at']);
            $table->dropIndex(['template_name']);
            $table->dropIndex(['status']);
            $table->dropColumn(['submission_id', 'resend_message_id']);
        });
    }
};
