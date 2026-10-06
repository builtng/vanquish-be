<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\TrainingCounsellor;
use App\Models\User;
use App\Models\ActivityLog;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\File;

class CleanupQ02DettolSmith extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'qc:cleanup-q02-dettol-smith {--no-backup : Skip database backup}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Q02 Data Cleanup: Archive QC002 test profile using standard archive code path, unlink and deactivate counsellor user, and record activity log';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info("Starting Q02 clean-up for QC002...");

        $results = self::executeCleanup(!$this->option('no-backup'));

        $this->info("Backup Path: " . ($results['backup_file'] ?? 'Skipped'));
        $this->info("QC002 Before: " . json_encode($results['before']['tc']));
        $this->info("QC002 After:  " . json_encode($results['after']['tc']));
        $this->info("User Before:  " . json_encode($results['before']['user']));
        $this->info("User After:   " . json_encode($results['after']['user']));
        $this->info("Documents:    " . json_encode($results['documents']));
        $this->info("Activity Log: " . ($results['activity_log']['description'] ?? ''));

        return Command::SUCCESS;
    }

    /**
     * Reusable clean-up logic callable from Command or Web Route.
     */
    public static function executeCleanup(bool $createBackup = true): array
    {
        $report = [
            'backup_file' => null,
            'before' => [],
            'after' => [],
            'documents' => [],
            'activity_log' => null,
        ];

        // 1. Back up database if requested
        if ($createBackup) {
            $report['backup_file'] = self::backupDatabase();
        }

        // 2. Find QC002
        $tc = TrainingCounsellor::withTrashed()->where('tc_id', 'QC002')->first();
        if (!$tc) {
            $tc = TrainingCounsellor::withTrashed()->find(10);
        }

        if (!$tc) {
            return [
                'skipped' => true,
                'message' => 'QC002 practitioner record not found in this environment.',
            ];
        }

        // 3. Find linked user
        $user = User::where('training_counsellor_id', $tc->id)
            ->orWhere('email', $tc->email)
            ->first();

        $hasIsActive = Schema::hasColumn('users', 'is_active');

        // Record BEFORE values
        $report['before'] = [
            'tc' => [
                'id' => $tc->id,
                'tc_id' => $tc->tc_id,
                'name' => $tc->name,
                'email' => $tc->email,
                'status' => $tc->status,
                'archived_at' => (string)$tc->archived_at,
                'deleted_at' => (string)$tc->deleted_at,
            ],
            'user' => $user ? [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
                'has_is_active_column' => $hasIsActive,
                'is_active' => $hasIsActive ? (bool)$user->is_active : 'N/A',
                'training_counsellor_id' => $user->training_counsellor_id,
            ] : null,
        ];

        // 4. Report stored file paths and upload timestamps for the five documents
        $docFields = [
            'qualification_document' => $tc->qualification_document,
            'dbs_certificate_qualified' => $tc->dbs_certificate_qualified,
            'insurance_qualified' => $tc->insurance_qualified,
            'self_employment_proof' => $tc->self_employment_proof,
            'professional_membership' => $tc->professional_membership,
        ];

        $docReport = [];
        foreach ($docFields as $field => $storedPath) {
            if (!$storedPath) {
                $docReport[$field] = [
                    'stored_path' => null,
                    'file_exists' => false,
                    'upload_timestamp' => null,
                ];
                continue;
            }

            $diskPath = storage_path('app/public/' . $storedPath);
            if (!file_exists($diskPath)) {
                $diskPath = storage_path('app/' . $storedPath);
            }
            if (!file_exists($diskPath)) {
                $diskPath = public_path($storedPath);
            }

            $exists = file_exists($diskPath);
            $docReport[$field] = [
                'stored_path' => $storedPath,
                'file_exists' => $exists,
                'upload_timestamp' => $exists ? date('Y-m-d H:i:s', filemtime($diskPath)) : (
                    $tc->updated_at ? (string)$tc->updated_at : null
                ),
                'file_size_bytes' => $exists ? filesize($diskPath) : null,
            ];
        }
        $report['documents'] = $docReport;

        // 5. Execute app archive action (exact code path as TrainingCounsellorController::destroy)
        // Does NOT rename or change email on QC002
        $now = now();
        $tc->archived_at = $now;
        $tc->save();

        // Unlink user association
        User::where('training_counsellor_id', $tc->id)->update([
            'training_counsellor_id' => null,
        ]);

        // Soft delete the counsellor record
        $tc->delete();

        // 6. Deactivate portal user if counsellor login and is_active column exists
        if ($user && $user->role === 'counsellor') {
            if ($hasIsActive) {
                $user->is_active = false;
                if (Schema::hasColumn('users', 'deactivated_at')) {
                    $user->deactivated_at = $now;
                }
                $user->save();
            }
        }

        // 7. Add activity_logs entry
        $logDescription = "QC002 archived: test profile merged with a 24 Sep test application (Q02 clean-up).";
        $log = ActivityLog::create([
            'user_id' => null,
            'action' => 'counsellor_archived',
            'model_type' => TrainingCounsellor::class,
            'model_id' => $tc->id,
            'description' => $logDescription,
            'ip_address' => request()->ip() ?? '127.0.0.1',
        ]);

        $report['activity_log'] = [
            'id' => $log->id,
            'action' => $log->action,
            'description' => $log->description,
            'created_at' => (string)$log->created_at,
        ];

        // 8. Record AFTER values
        $tcFresh = TrainingCounsellor::withTrashed()->find($tc->id);
        $userFresh = $user ? User::find($user->id) : null;

        $report['after'] = [
            'tc' => [
                'id' => $tcFresh->id,
                'tc_id' => $tcFresh->tc_id,
                'name' => $tcFresh->name,
                'email' => $tcFresh->email,
                'status' => $tcFresh->status,
                'archived_at' => (string)$tcFresh->archived_at,
                'deleted_at' => (string)$tcFresh->deleted_at,
            ],
            'user' => $userFresh ? [
                'id' => $userFresh->id,
                'name' => $userFresh->name,
                'email' => $userFresh->email,
                'role' => $userFresh->role,
                'has_is_active_column' => $hasIsActive,
                'is_active' => $hasIsActive ? (bool)$userFresh->is_active : 'N/A',
                'training_counsellor_id' => $userFresh->training_counsellor_id,
            ] : null,
        ];

        return $report;
    }

    /**
     * Pure PHP database dumper that creates a full SQL backup.
     */
    public static function backupDatabase(): string
    {
        $backupDir = storage_path('app/backups');
        if (!File::exists($backupDir)) {
            File::makeDirectory($backupDir, 0755, true);
        }

        $filename = 'backup_before_q02_' . date('Y_m_d_His') . '.sql';
        $filepath = $backupDir . '/' . $filename;

        $tables = [];
        $driver = DB::connection()->getDriverName();

        if ($driver === 'sqlite') {
            $tableRows = DB::select("SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%'");
            foreach ($tableRows as $row) {
                $tables[] = $row->name;
            }
        } else {
            $tableRows = DB::select('SHOW TABLES');
            foreach ($tableRows as $row) {
                $tables[] = array_values((array)$row)[0];
            }
        }

        $handle = fopen($filepath, 'w');
        fwrite($handle, "-- Database Backup before Q02 Cleanup\n");
        fwrite($handle, "-- Generated: " . date('Y-m-d H:i:s') . "\n\n");

        foreach ($tables as $table) {
            fwrite($handle, "-- Table: {$table}\n");

            if ($driver === 'sqlite') {
                $create = DB::select("SELECT sql FROM sqlite_master WHERE type='table' AND name=?", [$table]);
                if (!empty($create[0]->sql)) {
                    fwrite($handle, $create[0]->sql . ";\n\n");
                }
            } else {
                $create = DB::select("SHOW CREATE TABLE `{$table}`");
                if (!empty($create[0])) {
                    $createSql = ((array)$create[0])['Create Table'] ?? '';
                    fwrite($handle, "DROP TABLE IF EXISTS `{$table}`;\n" . $createSql . ";\n\n");
                }
            }

            // Dump data
            $rows = DB::table($table)->get();
            foreach ($rows as $row) {
                $array = (array)$row;
                $cols = array_keys($array);
                $vals = array_map(function ($val) {
                    if (is_null($val)) return 'NULL';
                    return "'" . addslashes((string)$val) . "'";
                }, array_values($array));

                $colList = implode(', ', array_map(fn($c) => "`{$c}`", $cols));
                $valList = implode(', ', $vals);
                fwrite($handle, "INSERT INTO `{$table}` ({$colList}) VALUES ({$valList});\n");
            }
            fwrite($handle, "\n");
        }

        fclose($handle);

        return $filepath;
    }
}
