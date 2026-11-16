<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\Client;
use App\Models\ClientTcMatch;
use App\Models\TrainingCounsellor;

/**
 * A client whose matched counsellor has been archived used to show
 * "Matched With Counsellor" with "Not assigned" under Matched To, and was
 * hidden from Pending Matches. This puts such clients back into Pending
 * Matches (the same as clicking "Unassign") so they can be re-matched.
 */
class OrphanedMatchRepair
{
    /** Release every client still matched to this (archived) counsellor. */
    public function releaseClientsOf(TrainingCounsellor $tc, ?int $userId = null): int
    {
        $count = 0;
        Client::where('matched_tc_id', $tc->id)->each(function (Client $client) use ($tc, $userId, &$count) {
            $this->release($client, $tc->id, "{$tc->name} ({$tc->tc_id}) was archived", $userId);
            $count++;
        });

        return $count;
    }

    /** Release clients whose matched counsellor no longer exists or is archived. */
    public function releaseAllOrphans(): int
    {
        $count = 0;
        Client::whereNotNull('matched_tc_id')
            ->whereDoesntHave('matchedTc')
            ->each(function (Client $client) use (&$count) {
                $this->release($client, $client->matched_tc_id, 'their matched counsellor is archived or missing', null);
                $count++;
            });

        return $count;
    }

    /**
     * Clients in a "Matched" stage with no counsellor at all (matched_tc_id
     * null). Returns them; when $apply is true they are moved back to
     * "Consultation Completed" so they reappear in Pending Matches.
     *
     * @return \Illuminate\Support\Collection<int, Client>
     */
    public function unassignedMatches(bool $apply, ?int $userId = null)
    {
        $clients = Client::whereNull('matched_tc_id')
            ->get()
            ->filter(fn (Client $c) => Client::isMatchedStage($c->stage))
            ->values();

        if ($apply) {
            foreach ($clients as $client) {
                $oldStage = $client->stage;
                $client->update(['stage' => 'Consultation Completed', 'matched_date' => null]);

                ActivityLog::create([
                    'user_id' => $userId,
                    'action' => 'client_stage_repaired',
                    'model_type' => Client::class,
                    'model_id' => $client->id,
                    'description' => "{$client->name} moved from \"{$oldStage}\" back to Pending Matches (Consultation Completed) because no counsellor was assigned",
                    'changes' => ['stage' => ['from' => $oldStage, 'to' => 'Consultation Completed']],
                ]);
            }
        }

        return $clients;
    }

    private function release(Client $client, int $tcId, string $reason, ?int $userId): void
    {
        ClientTcMatch::where('client_id', $client->id)
            ->where('tc_id', $tcId)
            ->where('status', 'assigned')
            ->update(['status' => 'cancelled']);

        $client->update([
            'matched_tc_id' => null,
            'matched_date' => null,
            'stage' => 'Consultation Completed', // back into Pending Matches
        ]);

        ActivityLog::create([
            'user_id' => $userId,
            'action' => 'client_tc_unmatched',
            'model_type' => Client::class,
            'model_id' => $client->id,
            'description' => "{$client->name} moved back to Pending Matches because {$reason}",
        ]);
    }
}
