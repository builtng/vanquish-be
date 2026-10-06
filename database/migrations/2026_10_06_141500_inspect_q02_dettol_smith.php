<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Artisan;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        try {
            Artisan::call('optimize:clear');
        } catch (\Throwable $e) {
            // ignore if optimize:clear fails in migration context
        }

        $tc = DB::table('training_counsellors')
            ->where('tc_id', 'QC002')
            ->first();

        // Also check if any other counsellor has Dettol or QC002
        $allMatching = DB::table('training_counsellors')
            ->where('tc_id', 'LIKE', '%QC%')
            ->orWhere('name', 'LIKE', '%Dettol%')
            ->orWhere('name', 'LIKE', '%Rooshan%')
            ->get();

        $output = [
            'qc002_found' => (bool)$tc,
            'qc002_details' => null,
            'qc_applications' => [],
            'activity_logs' => [],
            'all_matching_counsellors' => [],
        ];

        $docFields = [
            'qualification_document',
            'dbs_certificate_qualified',
            'insurance_qualified',
            'self_employment_proof',
            'professional_membership',
        ];

        if ($tc) {
            $tcArray = (array)$tc;
            $docPresence = [];
            foreach ($docFields as $docField) {
                if (isset($tcArray[$docField])) {
                    $docPresence[$docField . '_present'] = !empty($tcArray[$docField]);
                    unset($tcArray[$docField]); // DO NOT PRINT DOCUMENT CONTENTS
                }
            }
            $tcArray['document_presence'] = $docPresence;
            $output['qc002_details'] = $tcArray;

            // qc_applications
            $qcApps = DB::table('qc_applications')
                ->where('training_counsellor_id', $tc->id)
                ->orWhere('suggested_training_counsellor_id', $tc->id)
                ->orWhere('email', $tc->email)
                ->get();

            $output['qc_applications'] = $qcApps->map(function ($app) use ($docFields) {
                $appArr = (array)$app;
                foreach ($docFields as $docField) {
                    if (isset($appArr[$docField])) {
                        $appArr[$docField . '_present'] = !empty($appArr[$docField]);
                        unset($appArr[$docField]); // DO NOT PRINT DOCUMENT CONTENTS
                    }
                }
                return $appArr;
            })->toArray();

            // activity_logs
            $logs = DB::table('activity_logs')
                ->where(function ($q) use ($tc) {
                    $q->where('model_type', 'App\Models\TrainingCounsellor')
                      ->where('model_id', $tc->id);
                })
                ->orWhere('description', 'LIKE', '%QC002%')
                ->orWhere('description', 'LIKE', '%Dettol%')
                ->orWhere('description', 'LIKE', '%' . $tc->email . '%')
                ->orderBy('id', 'desc')
                ->get();

            $output['activity_logs'] = $logs->map(function ($log) use ($docFields) {
                $logArr = (array)$log;
                if (!empty($logArr['changes'])) {
                    $changes = json_decode($logArr['changes'], true);
                    if (is_array($changes)) {
                        foreach ($docFields as $df) {
                            if (isset($changes[$df])) $changes[$df] = '[OMITTED]';
                            if (isset($changes['attributes'][$df])) $changes['attributes'][$df] = '[OMITTED]';
                            if (isset($changes['old'][$df])) $changes['old'][$df] = '[OMITTED]';
                        }
                        $logArr['changes'] = $changes;
                    }
                }
                return $logArr;
            })->toArray();
        }

        $output['all_matching_counsellors'] = $allMatching->map(function ($item) {
            return [
                'id' => $item->id,
                'tc_id' => $item->tc_id,
                'name' => $item->name,
                'legal_first_name' => $item->legal_first_name ?? null,
                'legal_last_name' => $item->legal_last_name ?? null,
                'email' => $item->email,
                'status' => $item->status,
                'created_at' => $item->created_at,
                'updated_at' => $item->updated_at,
                'deleted_at' => $item->deleted_at ?? null,
                'archived_at' => $item->archived_at ?? null,
            ];
        })->toArray();

        $json = json_encode($output, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

        Log::info("Q02_CHECK_FIRST_RESULTS:\n" . $json);

        if ($this->command) {
            $this->command->line("=== Q02_INSPECTION_START ===");
            $this->command->line($json);
            $this->command->line("=== Q02_INSPECTION_END ===");
        } else {
            echo "=== Q02_INSPECTION_START ===\n" . $json . "\n=== Q02_INSPECTION_END ===\n";
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Read-only migration
    }
};
