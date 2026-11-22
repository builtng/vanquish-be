<?php

namespace App\Console\Commands;

use App\Models\ActivityLog;
use App\Models\TrainingCounsellor;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * One-off repair: counsellors whose portal login was left unlinked or
 * deactivated after a duplicate record with the same email was archived
 * ("account deactivated" although the counsellor is active).
 *
 * For each active (not archived) counsellor, the login with their email is
 * re-linked to them and switched back on. Staff logins are never touched,
 * and a login already linked to a different active counsellor is only
 * reported.
 */
class RepairCounsellorLogins extends Command
{
    protected $signature = 'vqt:repair-counsellor-logins {--dry-run : List what would change without changing anything}';

    protected $description = 'Re-link and reactivate the portal logins of active counsellors (left broken by archiving a duplicate record)';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $rows = [];
        $fixed = 0;

        TrainingCounsellor::whereNull('archived_at')->orderBy('id')->each(function (TrainingCounsellor $tc) use ($dryRun, &$rows, &$fixed) {
            $email = strtolower(trim((string) $tc->email));
            if ($email === '') {
                return;
            }
            $user = User::whereRaw('LOWER(email) = ?', [$email])->first();

            if (!$user) {
                // Nothing to repair: "Send portal invite" creates the login.
                return;
            }
            if ($user->isStaff()) {
                $rows[] = [$tc->tc_id, $tc->name, $user->email, "Email belongs to a {$user->role} login: left alone"];
                return;
            }

            $problems = [];
            $linkedElsewhere = $user->training_counsellor_id && (int) $user->training_counsellor_id !== (int) $tc->id
                && TrainingCounsellor::whereKey($user->training_counsellor_id)->whereNull('archived_at')->exists();
            if ($linkedElsewhere) {
                $rows[] = [$tc->tc_id, $tc->name, $user->email, "Linked to another active counsellor (#{$user->training_counsellor_id}): left alone"];
                return;
            }
            if ((int) $user->training_counsellor_id !== (int) $tc->id) {
                $problems[] = 'not linked';
            }
            if (!$user->isActive()) {
                $problems[] = 'deactivated';
            }
            if ($user->role !== 'counsellor') {
                $problems[] = "role was '{$user->role}'";
            }
            if (!$problems) {
                return;
            }

            $what = implode(', ', $problems);
            $rows[] = [$tc->tc_id, $tc->name, $user->email, ($dryRun ? 'Would fix: ' : 'Fixed: ') . $what];

            if ($dryRun) {
                return;
            }

            $user->update([
                'role' => 'counsellor',
                'training_counsellor_id' => $tc->id,
                'is_active' => true,
                'deactivated_at' => null,
            ]);
            ActivityLog::create([
                'user_id' => null,
                'action' => 'counsellor_login_repaired',
                'model_type' => TrainingCounsellor::class,
                'model_id' => $tc->id,
                'description' => "Portal login {$user->email} re-linked to {$tc->name} ({$tc->tc_id}) and switched on ({$what})",
            ]);
            Log::info("vqt:repair-counsellor-logins fixed {$user->email} for {$tc->tc_id}: {$what}");
            $fixed++;
        });

        if (!$rows) {
            $this->info('Every active counsellor with a login is linked and switched on. Nothing to do.');
            return self::SUCCESS;
        }

        $this->table(['Counsellor', 'Name', 'Login', 'Result'], $rows);
        $this->info($dryRun
            ? 'Dry run: nothing was changed. Run again without --dry-run to apply the fixes.'
            : "{$fixed} login(s) fixed.");

        return self::SUCCESS;
    }
}
