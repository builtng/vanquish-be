<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('clients')
            ->whereIn('stage', [
                'Application',
                'Application Submitted',
                'Assessment Submitted',
                'Pending Application Review',
                'Application & Assessment form Submitted',
            ])
            ->update(['stage' => 'Consultation Booked']);

        DB::table('clients')->where('stage', 'Matched with TC')->update(['stage' => 'Matched With Counsellor']);
        DB::table('clients')->where('stage', 'Matched')->update(['stage' => 'Matched With Counsellor']);
        DB::table('clients')->where('stage', 'Pending Match')->update(['stage' => 'Agreement Signed']);
        DB::table('clients')->where('stage', 'Agreement Pending')->update(['stage' => 'Agreement Sent']);
        DB::table('clients')->where('stage', 'Sessions Bookable')->update(['stage' => 'Sessions Booked']);
        DB::table('clients')->whereNull('stage')->update(['stage' => 'Consultation Booked']);

        if (Schema::hasTable('clients') && Schema::hasColumn('clients', 'stage')) {
            Schema::table('clients', function (Blueprint $table) {
                $table->string('stage')->default('Consultation Booked')->change();
            });
        }
    }

    public function down(): void
    {
        DB::table('clients')->where('stage', 'Matched With Counsellor')->update(['stage' => 'Matched with TC']);
        DB::table('clients')->where('stage', 'Sessions Booked')->update(['stage' => 'Sessions Bookable']);

        if (Schema::hasTable('clients') && Schema::hasColumn('clients', 'stage')) {
            Schema::table('clients', function (Blueprint $table) {
                $table->string('stage')->default('Application & Assessment form Submitted')->change();
            });
        }
    }
};
