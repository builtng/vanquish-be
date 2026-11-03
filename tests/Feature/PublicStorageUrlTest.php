<?php

namespace Tests\Feature;

use App\Models\TrainingCounsellor;
use App\Models\User;
use App\Support\PublicStorageUrl;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PublicStorageUrlTest extends TestCase
{
    use RefreshDatabase;

    private const PHOTO = 'tc_photos/37/1793707437_6ae9cdad13c1b_dc03303b-eafa-4d3c-bd46-b04c1b2b875d.png';

    protected function setUp(): void
    {
        parent::setUp();
        // Production: APP_URL and FRONTEND_URL are the website, files are served by the API.
        config([
            'app.url' => 'https://vqtmanagement.com',
            'app.frontend_url' => 'https://vqtmanagement.com',
            'app.frontend_urls' => ['https://vqtmanagement.com'],
            'app.storage_url' => null,
        ]);
    }

    public function test_photo_url_uses_storage_url_setting_when_set(): void
    {
        config(['app.storage_url' => 'https://api.vqtmanagement.com/storage/']);
        $tc = new TrainingCounsellor(['photo' => self::PHOTO]);

        $this->assertSame('https://api.vqtmanagement.com/storage/' . self::PHOTO, $tc->photo_url);
    }

    public function test_photo_url_uses_the_api_host_not_app_url(): void
    {
        $this->app['request']->headers->set('HOST', 'api.vqtmanagement.com');
        $this->app['request']->server->set('HTTPS', 'on');

        $tc = new TrainingCounsellor(['photo' => self::PHOTO]);

        $this->assertSame('https://api.vqtmanagement.com/storage/' . self::PHOTO, $tc->photo_url);
    }

    public function test_old_links_saved_against_the_website_are_corrected(): void
    {
        config(['app.storage_url' => 'https://api.vqtmanagement.com/storage']);

        $this->assertSame(
            'https://api.vqtmanagement.com/storage/' . self::PHOTO,
            PublicStorageUrl::for('https://vqtmanagement.com/storage/' . self::PHOTO)
        );
        $this->assertSame(
            'https://api.vqtmanagement.com/storage/' . self::PHOTO,
            PublicStorageUrl::for('https://www.vqtmanagement.com/storage/' . self::PHOTO)
        );
        // External links are left alone.
        $this->assertSame('https://cdn.example.com/a.png', PublicStorageUrl::for('https://cdn.example.com/a.png'));
        $this->assertNull(PublicStorageUrl::for(null));
    }

    public function test_counsellor_photo_upload_and_user_endpoint_return_the_api_link(): void
    {
        Storage::fake('public');
        config(['app.storage_url' => 'https://api.vqtmanagement.com/storage']);

        $tc = TrainingCounsellor::create(['tc_id' => 'QC007', 'name' => 'Pat Doe', 'email' => 'pat@example.com', 'status' => 'Active']);
        $user = User::factory()->create(['role' => 'counsellor', 'email' => 'pat@example.com', 'training_counsellor_id' => $tc->id]);

        $upload = $this->actingAs($user)->post('/api/counsellor/upload-photo', [
            'photo' => UploadedFile::fake()->image('me.png', 200, 200),
        ]);


        $upload->assertStatus(200);
        $this->assertStringStartsWith('https://api.vqtmanagement.com/storage/', $upload->json('photo_url'));
        $this->assertStringStartsWith('https://api.vqtmanagement.com/storage/', $upload->json('tc.photo_url'));
    }
}
