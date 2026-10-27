<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreatePersonalTherapyTokensTable extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (!Schema::hasTable('personal_therapy_tokens')) {
            Schema::create('personal_therapy_tokens', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('trainee_application_id');
                $table->string('token', 128)->unique();
                $table->string('signature', 128)->nullable();
                $table->timestamp('expires_at');
                $table->timestamp('used_at')->nullable();
                $table->timestamps();

                $table->index(['trainee_application_id']);
                $table->index(['token', 'expires_at']);
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('personal_therapy_tokens');
    }
}

return new CreatePersonalTherapyTokensTable;
