<?php

namespace App\Console\Commands;

use App\Services\OrphanedMatchRepair;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * One-off repair: clients showing a "Matched" stage with no counsellor
 * (matched_tc_id null) go back to "Consultation Completed" so they
 * reappear in Pending Matches.
 */
class RepairUnassignedMatches extends Command
{
    protected $signature = 'vqt:repair-unassigned-matches {--dry-run : List the clients without changing anything}';

    protected $description = 'Move clients in a Matched stage with no counsellor back to Consultation Completed (Pending Matches)';

    public function handle(OrphanedMatchRepair $repair): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $clients = $repair->unassignedMatches(false);
        $oldStages = $clients->mapWithKeys(fn ($c) => [$c->id => $c->stage]);

        if (!$dryRun) {
            $clients = $repair->unassignedMatches(true);
        }

        if ($clients->isEmpty()) {
            $this->info('No clients are in a Matched stage without a counsellor.');
            return self::SUCCESS;
        }

        $this->table(
            ['ID', 'Client ID', 'Name', 'Current stage', 'New stage'],
            $clients->map(fn ($c) => [
                $c->id,
                $c->client_id,
                $c->name,
                $oldStages[$c->id] ?? $c->stage,
                'Consultation Completed',
            ])->all()
        );

        foreach ($clients as $c) {
            $line = ($dryRun ? '[dry-run] would move ' : 'Moved ') . "client {$c->id} ({$c->client_id}, {$c->name}) to Consultation Completed";
            Log::info('vqt:repair-unassigned-matches ' . $line);
        }

        $this->info(($dryRun ? 'Dry run: ' . $clients->count() . ' client(s) would be moved. Nothing was changed.'
            : $clients->count() . ' client(s) moved back to Pending Matches.'));

        return self::SUCCESS;
    }
}
