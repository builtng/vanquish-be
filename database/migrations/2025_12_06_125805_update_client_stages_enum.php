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
        // Map old stage values to new stage values
        $stageMapping = [
            'Application' => 'Application & Assessment form Submitted',
            'Consultation Booked' => 'Consultation Booked',
            'Consultation Completed' => 'Consultation Completed',
            'Pending Match' => 'Matched with TC', // Map Pending Match to Matched with TC
            'Matched' => 'Matched with TC',
            'Agreement Pending' => 'Agreement Sent', // Map Agreement Pending to Agreement Sent
            'Active Therapy' => 'Active Therapy',
            'Completed' => 'Active Therapy', // Map Completed to Active Therapy (or keep as is, but user didn't mention it)
        ];

        // Update existing records
        foreach ($stageMapping as $oldStage => $newStage) {
            DB::table('clients')
                ->where('stage', $oldStage)
                ->update(['stage' => $newStage]);
        }

        // For SQLite, we can't modify enum constraints, but we can update the column type comment
        // The actual constraint enforcement will be in the application layer
        if (DB::getDriverName() !== 'sqlite') {
            // For MySQL/PostgreSQL, modify the enum column
            DB::statement("ALTER TABLE clients MODIFY COLUMN stage ENUM(
                'Application & Assessment form Submitted',
                'Consultation Booked',
                'Consultation Completed',
                'Matched with TC',
                'Agreement Sent',
                'Agreement Signed',
                'Sessions Bookable',
                'Active Therapy'
            ) DEFAULT 'Application & Assessment form Submitted'");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Reverse mapping
        $reverseMapping = [
            'Application & Assessment form Submitted' => 'Application',
            'Consultation Booked' => 'Consultation Booked',
            'Consultation Completed' => 'Consultation Completed',
            'Matched with TC' => 'Matched',
            'Agreement Sent' => 'Agreement Pending',
            'Agreement Signed' => 'Agreement Pending',
            'Sessions Bookable' => 'Agreement Pending',
            'Active Therapy' => 'Active Therapy',
        ];

        foreach ($reverseMapping as $newStage => $oldStage) {
            DB::table('clients')
                ->where('stage', $newStage)
                ->update(['stage' => $oldStage]);
        }

        if (DB::getDriverName() !== 'sqlite') {
            DB::statement("ALTER TABLE clients MODIFY COLUMN stage ENUM(
                'Application',
                'Consultation Booked',
                'Consultation Completed',
                'Pending Match',
                'Matched',
                'Agreement Pending',
                'Active Therapy',
                'Completed'
            ) DEFAULT 'Application'");
        }
    }
};
