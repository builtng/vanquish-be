<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Folder;
use App\Models\FolderShare;
use App\Models\SharedDocument;
use App\Models\TrainingCounsellor;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SharedDocumentAccessSecurityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        Storage::fake('private');
    }

    public function test_unauthenticated_user_cannot_access_contact_files_or_download_shared_documents()
    {
        $client = Client::create([
            'name' => 'John Doe',
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email' => 'john.doe@example.com',
            'phone' => '07111222333',
            'status' => 'active',
        ]);

        $response = $this->getJson("/api/contacts/client/{$client->id}/files");
        $response->assertStatus(401);

        $uploader = User::create([
            'name' => 'Uploader Admin',
            'email' => 'uploader@example.com',
            'password' => bcrypt('password'),
            'role' => 'admin',
        ]);

        $doc = SharedDocument::create([
            'client_id' => $client->id,
            'category' => 'private',
            'name' => 'Private Case Notes.pdf',
            'file_path' => 'shared_documents/private_case_notes.pdf',
            'file_type' => 'PDF',
            'file_size' => '10 KB',
            'uploaded_by' => $uploader->id,
        ]);

        $downloadResp = $this->getJson("/api/shared-documents/{$doc->id}/download");
        $downloadResp->assertStatus(401);
    }

    public function test_counsellor_cannot_list_contact_files_for_client_or_counsellor()
    {
        $counsellorUser = User::create([
            'name' => 'Counsellor User',
            'email' => 'counsellor@example.com',
            'password' => bcrypt('password'),
            'role' => 'counsellor',
        ]);

        $tc = TrainingCounsellor::create([
            'user_id' => $counsellorUser->id,
            'name' => 'Counsellor User',
            'first_name' => 'Counsellor',
            'last_name' => 'User',
            'email' => 'counsellor@example.com',
            'phone' => '07111222444',
            'status' => 'active',
        ]);
        $counsellorUser->update(['training_counsellor_id' => $tc->id]);

        $client = Client::create([
            'name' => 'Jane Client',
            'first_name' => 'Jane',
            'last_name' => 'Client',
            'email' => 'jane.client@example.com',
            'phone' => '07111222555',
            'status' => 'active',
        ]);

        // Attempt listing client files
        $response = $this->actingAs($counsellorUser)->getJson("/api/contacts/client/{$client->id}/files");
        $response->assertStatus(403);

        // Attempt listing client private files specifically
        $privateResp = $this->actingAs($counsellorUser)->getJson("/api/contacts/client/{$client->id}/files?folder_id=private");
        $privateResp->assertStatus(403);

        // Attempt listing another TC's files
        $otherTc = TrainingCounsellor::create([
            'name' => 'Other TC',
            'first_name' => 'Other',
            'last_name' => 'TC',
            'email' => 'other.tc@example.com',
            'phone' => '07111222666',
            'status' => 'active',
        ]);
        $tcResp = $this->actingAs($counsellorUser)->getJson("/api/contacts/tc/{$otherTc->id}/files");
        $tcResp->assertStatus(403);

        // Accessing own contact files succeeds with 200
        $ownResp = $this->actingAs($counsellorUser)->getJson("/api/contacts/tc/{$tc->id}/files");
        $ownResp->assertStatus(200);
    }

    public function test_staff_can_list_contact_files_including_private_files()
    {
        $staffUser = User::create([
            'name' => 'Staff Member',
            'email' => 'staff@example.com',
            'password' => bcrypt('password'),
            'role' => 'staff',
        ]);

        $client = Client::create([
            'name' => 'Mark Client',
            'first_name' => 'Mark',
            'last_name' => 'Client',
            'email' => 'mark.client@example.com',
            'phone' => '07111222777',
            'status' => 'active',
        ]);

        $response = $this->actingAs($staffUser)->getJson("/api/contacts/client/{$client->id}/files");
        $response->assertStatus(200)
            ->assertJsonStructure(['folders', 'files', 'contact']);

        $privateResp = $this->actingAs($staffUser)->getJson("/api/contacts/client/{$client->id}/files?folder_id=private");
        $privateResp->assertStatus(200)
            ->assertJsonStructure(['folders', 'files', 'current_category']);
    }

    public function test_counsellor_cannot_download_private_category_files()
    {
        $admin = User::create([
            'name' => 'Admin User',
            'email' => 'admin@example.com',
            'password' => bcrypt('password'),
            'role' => 'admin',
        ]);

        $counsellorUser = User::create([
            'name' => 'Sneaky Counsellor',
            'email' => 'sneaky@example.com',
            'password' => bcrypt('password'),
            'role' => 'counsellor',
        ]);

        Storage::disk('public')->put('shared_documents/confidential.pdf', 'Confidential Data');

        $doc = SharedDocument::create([
            'category' => 'private',
            'name' => 'Confidential Review.pdf',
            'file_path' => 'shared_documents/confidential.pdf',
            'file_type' => 'PDF',
            'file_size' => '17 B',
            'uploaded_by' => $admin->id,
        ]);

        // Counsellor forbidden
        $response = $this->actingAs($counsellorUser)->getJson("/api/shared-documents/{$doc->id}/download");
        $response->assertStatus(403);

        // Staff allowed
        $staffResp = $this->actingAs($admin)->get("/api/shared-documents/{$doc->id}/download");
        $staffResp->assertStatus(200);
    }

    public function test_counsellor_can_download_files_shared_with_them_or_uploaded_by_them()
    {
        $admin = User::create([
            'name' => 'Admin User',
            'email' => 'admin2@example.com',
            'password' => bcrypt('password'),
            'role' => 'admin',
        ]);

        $counsellorUser = User::create([
            'name' => 'Good Counsellor',
            'email' => 'good@example.com',
            'password' => bcrypt('password'),
            'role' => 'counsellor',
        ]);

        // Folder shared with all trainees
        $folder = Folder::create([
            'name' => 'Trainee Handbooks',
            'type' => 'shared',
            'created_by' => $admin->id,
        ]);
        FolderShare::create([
            'folder_id' => $folder->id,
            'target_type' => 'all_trainees',
        ]);

        Storage::disk('public')->put('shared_documents/handbook.pdf', 'Handbook Content');

        $sharedDoc = SharedDocument::create([
            'folder_id' => $folder->id,
            'name' => 'Handbook.pdf',
            'file_path' => 'shared_documents/handbook.pdf',
            'file_type' => 'PDF',
            'file_size' => '16 B',
            'uploaded_by' => $admin->id,
        ]);

        $response = $this->actingAs($counsellorUser)->get("/api/shared-documents/{$sharedDoc->id}/download");
        $response->assertStatus(200);

        // File uploaded by counsellor themselves
        Storage::disk('public')->put('shared_documents/my_notes.pdf', 'My Own Notes');
        $ownDoc = SharedDocument::create([
            'category' => 'uploaded',
            'name' => 'My Notes.pdf',
            'file_path' => 'shared_documents/my_notes.pdf',
            'file_type' => 'PDF',
            'file_size' => '12 B',
            'uploaded_by' => $counsellorUser->id,
        ]);

        $ownResp = $this->actingAs($counsellorUser)->get("/api/shared-documents/{$ownDoc->id}/download");
        $ownResp->assertStatus(200);
    }
}
