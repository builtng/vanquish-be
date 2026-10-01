<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use App\Models\Client;
use App\Models\TrainingCounsellor;

class GenerateUuids extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'uuid:generate {--table=all : Table to generate UUIDs for (clients, training_counsellors, or all)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate UUIDs for all records that do not have them';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $table = $this->option('table');

        if ($table === 'all' || $table === 'clients') {
            $this->generateClientUuids();
        }

        if ($table === 'all' || $table === 'training_counsellors') {
            $this->generateTrainingCounsellorUuids();
        }

        $this->info('UUID generation completed!');
    }

    /**
     * Generate UUIDs for clients
     */
    protected function generateClientUuids()
    {
        $this->info('Generating UUIDs for clients...');

        // Check if uuid column exists
        if (!Schema::hasColumn('clients', 'uuid')) {
            $this->warn('UUID column does not exist in clients table. Please run the migration first.');
            return;
        }

        // Get all clients without UUIDs
        $clientsWithoutUuid = DB::table('clients')
            ->whereNull('uuid')
            ->orWhere('uuid', '')
            ->get();

        if ($clientsWithoutUuid->isEmpty()) {
            $this->info('All clients already have UUIDs.');
            return;
        }

        $bar = $this->output->createProgressBar($clientsWithoutUuid->count());
        $bar->start();

        foreach ($clientsWithoutUuid as $client) {
            DB::table('clients')
                ->where('id', $client->id)
                ->update(['uuid' => Str::uuid()->toString()]);
            
            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
        $this->info("Generated UUIDs for {$clientsWithoutUuid->count()} client(s).");
    }

    /**
     * Generate UUIDs for training counsellors
     */
    protected function generateTrainingCounsellorUuids()
    {
        $this->info('Generating UUIDs for training counsellors...');

        // Check if uuid column exists
        if (!Schema::hasColumn('training_counsellors', 'uuid')) {
            $this->warn('UUID column does not exist in training_counsellors table. Please run the migration first.');
            return;
        }

        // Get all training counsellors without UUIDs
        $tcsWithoutUuid = DB::table('training_counsellors')
            ->whereNull('uuid')
            ->orWhere('uuid', '')
            ->get();

        if ($tcsWithoutUuid->isEmpty()) {
            $this->info('All training counsellors already have UUIDs.');
            return;
        }

        $bar = $this->output->createProgressBar($tcsWithoutUuid->count());
        $bar->start();

        foreach ($tcsWithoutUuid as $tc) {
            DB::table('training_counsellors')
                ->where('id', $tc->id)
                ->update(['uuid' => Str::uuid()->toString()]);
            
            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
        $this->info("Generated UUIDs for {$tcsWithoutUuid->count()} training counsellor(s).");
    }
}

