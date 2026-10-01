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
        Schema::create('shared_documents', function (Blueprint $blueprint) {
            $blueprint->id();
            $blueprint->string('name');
            $blueprint->text('description')->nullable();
            $blueprint->string('file_path');
            $blueprint->string('file_type')->nullable();
            $blueprint->string('file_size')->nullable();
            $blueprint->foreignId('uploaded_by')->constrained('users')->onDelete('cascade');
            $blueprint->boolean('is_active')->default(true);
            $blueprint->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('shared_documents');
    }
};
