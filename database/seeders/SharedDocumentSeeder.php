<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\SharedDocument;
use App\Models\User;

class SharedDocumentSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $admin = User::where('role', 'admin')->first();
        if (!$admin) return;

        $docs = [
            [
                'name' => 'Counsellor_Handbook_2024',
                'description' => 'Essential guidelines for all counsellors.',
                'file_path' => 'shared_documents/handbook.pdf',
                'file_type' => 'PDF',
                'file_size' => '1.2 MB',
                'uploaded_by' => $admin->id,
            ],
            [
                'name' => 'Standard_Operating_Procedures',
                'description' => 'Clinical SOPs for Vanquish Therapy.',
                'file_path' => 'shared_documents/sop.pdf',
                'file_type' => 'PDF',
                'file_size' => '850 KB',
                'uploaded_by' => $admin->id,
            ],
            [
                'name' => 'Emergency_Contact_Protocol',
                'description' => 'Protocol for handling client emergencies.',
                'file_path' => 'shared_documents/emergency.pdf',
                'file_type' => 'PDF',
                'file_size' => '420 KB',
                'uploaded_by' => $admin->id,
            ],
        ];

        foreach ($docs as $doc) {
            SharedDocument::create($doc);
        }
    }
}
