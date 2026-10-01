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
        // For shared_documents
        if (!Schema::hasColumn('shared_documents', 'client_id')) {
            Schema::table('shared_documents', function (Blueprint $table) {
                $table->foreignId('client_id')->nullable()->after('folder_id')->constrained('clients')->onDelete('cascade');
                $table->foreignId('tc_id')->nullable()->after('client_id')->constrained('training_counsellors')->onDelete('cascade');
                $table->enum('category', ['private', 'shared', 'uploaded'])->nullable()->after('tc_id');
            });
        }

        // For folders
        if (!Schema::hasColumn('folders', 'client_id')) {
            Schema::table('folders', function (Blueprint $table) {
                $table->foreignId('client_id')->nullable()->after('parent_id')->constrained('clients')->onDelete('cascade');
                $table->foreignId('tc_id')->nullable()->after('client_id')->constrained('training_counsellors')->onDelete('cascade');
                $table->enum('category', ['private', 'shared', 'uploaded'])->nullable()->after('tc_id');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('folders', function (Blueprint $table) {
            $table->dropForeign(['client_id']);
            $table->dropForeign(['tc_id']);
            $table->dropColumn(['client_id', 'tc_id', 'category']);
        });

        Schema::table('shared_documents', function (Blueprint $table) {
            $table->dropForeign(['client_id']);
            $table->dropForeign(['tc_id']);
            $table->dropColumn(['client_id', 'tc_id', 'category']);
        });
    }
};
