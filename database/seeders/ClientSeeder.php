<?php

namespace Database\Seeders;

use App\Models\Client;
use Illuminate\Database\Seeder;
use Carbon\Carbon;
use Illuminate\Support\Str;

class ClientSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $clients = []; // Removed all mock client data

        foreach ($clients as $clientData) {
            $client = Client::updateOrCreate(
                ['client_id' => $clientData['client_id']],
                $clientData
            );
        }
    }
}
