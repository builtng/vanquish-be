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
        Schema::create('folders', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->foreignId('parent_id')->nullable()->constrained('folders')->onDelete('cascade');
            $table->enum('type', ['internal', 'shared'])->default('internal');
            $table->foreignId('created_by')->constrained('users')->onDelete('cascade');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('folder_shares', function (Blueprint $table) {
            $table->id();
            $table->foreignId('folder_id')->constrained('folders')->onDelete('cascade');
            $table->string('target_type'); // e.g. 'all_trainees', 'all_qualified', 'individual_client', 'individual_user'
            $table->unsignedBigInteger('target_id')->nullable(); // Client ID or User ID
            $table->timestamps();
        });

        Schema::table('shared_documents', function (Blueprint $table) {
            $table->unsignedBigInteger('folder_id')->nullable()->after('id');
            $table->foreign('folder_id')->references('id')->on('folders')->onDelete('set null');
            $table->boolean('is_pinned')->default(false)->after('is_active');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('shared_documents', function (Blueprint $table) {
            $table->dropForeign(['folder_id']);
            $table->dropColumn(['folder_id', 'is_pinned']);
        });

        Schema::dropIfExists('folder_shares');
        Schema::dropIfExists('folders');
        Schema::dropIfExists('folders_and_shares_tables');
    }
};
