<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Clean up any existing duplicate consultation slots before creating unique index
        if (Schema::hasTable('consultation_slots')) {
            // Find duplicates by consultation_datetime and type
            $duplicates = DB::table('consultation_slots')
                ->select('consultation_datetime', 'type', DB::raw('COUNT(*) as count'))
                ->groupBy('consultation_datetime', 'type')
                ->having('count', '>', 1)
                ->get();

            foreach ($duplicates as $dup) {
                // Get all rows for this combination ordered by booked_slots DESC, id DESC
                $rows = DB::table('consultation_slots')
                    ->where('consultation_datetime', $dup->consultation_datetime)
                    ->where('type', $dup->type)
                    ->orderBy('booked_slots', 'desc')
                    ->orderBy('id', 'desc')
                    ->get();

                // Keep the first (one with most bookings/highest id), delete the rest
                $keepId = $rows->first()->id;
                $deleteIds = $rows->slice(1)->pluck('id')->toArray();

                if (!empty($deleteIds)) {
                    // Update any references in consultations table if needed
                    DB::table('consultations')
                        ->whereIn('consultation_slot_id', $deleteIds)
                        ->update(['consultation_slot_id' => $keepId]);

                    DB::table('consultation_slots')
                        ->whereIn('id', $deleteIds)
                        ->delete();
                }
            }

            // Add unique index on (consultation_datetime, type)
            Schema::table('consultation_slots', function (Blueprint $table) {
                $table->unique(['consultation_datetime', 'type'], 'consultation_slots_datetime_type_unique');
            });
        }

        // 2. Create consultation_days_off table
        if (!Schema::hasTable('consultation_days_off')) {
            Schema::create('consultation_days_off', function (Blueprint $table) {
                $table->id();
                $table->date('date')->unique();
                $table->string('reason')->nullable();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('consultation_slots')) {
            Schema::table('consultation_slots', function (Blueprint $table) {
                $table->dropUnique('consultation_slots_datetime_type_unique');
            });
        }

        Schema::dropIfExists('consultation_days_off');
    }
};
