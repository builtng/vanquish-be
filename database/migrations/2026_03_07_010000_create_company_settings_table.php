<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('company_settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->text('value')->nullable();
            $table->timestamps();
        });

        // Seed defaults
        $defaults = [
            ['key' => 'company_name',        'value' => 'Vanquish Training'],
            ['key' => 'company_tagline',      'value' => 'Professional Training Management'],
            ['key' => 'company_email',        'value' => ''],
            ['key' => 'company_phone',        'value' => ''],
            ['key' => 'company_address',      'value' => ''],
            ['key' => 'company_website',      'value' => ''],
            ['key' => 'pdf_header_text',      'value' => 'Vanquish Training Management System'],
            ['key' => 'pdf_footer_text',      'value' => 'Confidential — For internal use only'],
            ['key' => 'platform_logo_url',    'value' => ''],
            ['key' => 'platform_logo_dark_url','value' => ''],
        ];

        foreach ($defaults as $row) {
            DB::table('company_settings')->insert(array_merge($row, [
                'created_at' => now(),
                'updated_at' => now(),
            ]));
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('company_settings');
    }
};
