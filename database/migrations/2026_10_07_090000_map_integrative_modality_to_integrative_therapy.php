<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Helper to replace whole-word "Integrative" or "Integrative Counselling and Therapy"
     * in a comma-separated string, preserving other modalities and not touching
     * "Integrative Therapy".
     */
    private function updateModalityString(?string $val): ?string
    {
        if ($val === null || trim($val) === '') {
            return $val;
        }

        $parts = array_map('trim', explode(',', $val));
        $changed = false;
        $updatedParts = [];

        foreach ($parts as $part) {
            if ($part === 'Integrative' || $part === 'Integrative Counselling and Therapy') {
                $updatedParts[] = 'Integrative Therapy';
                $changed = true;
            } else {
                $updatedParts[] = $part;
            }
        }

        if (!$changed) {
            return $val;
        }

        // De-duplicate in case both 'Integrative' and 'Integrative Therapy' were present
        $updatedParts = array_values(array_unique($updatedParts));
        return implode(', ', $updatedParts);
    }

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. training_counsellors.modality (comma-separated string or single value)
        if (Schema::hasTable('training_counsellors') && Schema::hasColumn('training_counsellors', 'modality')) {
            $tcs = DB::table('training_counsellors')
                ->whereNotNull('modality')
                ->select('id', 'modality')
                ->get();

            foreach ($tcs as $tc) {
                $updated = $this->updateModalityString($tc->modality);
                if ($updated !== $tc->modality) {
                    DB::table('training_counsellors')
                        ->where('id', $tc->id)
                        ->update(['modality' => $updated]);
                }
            }
        }

        // 2. qc_applications.answers (JSON column containing modalities array or string)
        if (Schema::hasTable('qc_applications') && Schema::hasColumn('qc_applications', 'answers')) {
            $qcApps = DB::table('qc_applications')
                ->whereNotNull('answers')
                ->select('id', 'answers')
                ->get();

            foreach ($qcApps as $app) {
                $raw = $app->answers;
                $answers = is_string($raw) ? json_decode($raw, true) : (array) $raw;
                if (!is_array($answers)) {
                    continue;
                }

                $changed = false;

                // Handle modalities array or string in answers
                if (isset($answers['modalities'])) {
                    if (is_array($answers['modalities'])) {
                        $newModalities = [];
                        foreach ($answers['modalities'] as $m) {
                            if ($m === 'Integrative' || $m === 'Integrative Counselling and Therapy') {
                                $newModalities[] = 'Integrative Therapy';
                                $changed = true;
                            } else {
                                $newModalities[] = $m;
                            }
                        }
                        if ($changed) {
                            $answers['modalities'] = array_values(array_unique($newModalities));
                        }
                    } elseif (is_string($answers['modalities'])) {
                        $newModalityStr = $this->updateModalityString($answers['modalities']);
                        if ($newModalityStr !== $answers['modalities']) {
                            $answers['modalities'] = $newModalityStr;
                            $changed = true;
                        }
                    }
                }

                if ($changed) {
                    DB::table('qc_applications')
                        ->where('id', $app->id)
                        ->update(['answers' => json_encode($answers)]);
                }
            }
        }

        // 3. clients
        if (Schema::hasTable('clients')) {
            $col = Schema::hasColumn('clients', 'preferred_modality') ? 'preferred_modality' : (Schema::hasColumn('clients', 'modality') ? 'modality' : null);
            if ($col) {
                $clients = DB::table('clients')->whereNotNull($col)->select('id', $col)->get();
                foreach ($clients as $client) {
                    $updated = $this->updateModalityString($client->{$col});
                    if ($updated !== $client->{$col}) {
                        DB::table('clients')->where('id', $client->id)->update([$col => $updated]);
                    }
                }
            }
        }

        // 4. consultations.recommended_modality
        if (Schema::hasTable('consultations') && Schema::hasColumn('consultations', 'recommended_modality')) {
            $consultations = DB::table('consultations')
                ->whereNotNull('recommended_modality')
                ->select('id', 'recommended_modality')
                ->get();

            foreach ($consultations as $cons) {
                $updated = $this->updateModalityString($cons->recommended_modality);
                if ($updated !== $cons->recommended_modality) {
                    DB::table('consultations')
                        ->where('id', $cons->id)
                        ->update(['recommended_modality' => $updated]);
                }
            }
        }

        // 5. tc_intake_forms.modality
        if (Schema::hasTable('tc_intake_forms') && Schema::hasColumn('tc_intake_forms', 'modality')) {
            $forms = DB::table('tc_intake_forms')
                ->whereNotNull('modality')
                ->select('id', 'modality')
                ->get();

            foreach ($forms as $form) {
                $updated = $this->updateModalityString($form->modality);
                if ($updated !== $form->modality) {
                    DB::table('tc_intake_forms')
                        ->where('id', $form->id)
                        ->update(['modality' => $updated]);
                }
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // No action needed for down
    }
};
