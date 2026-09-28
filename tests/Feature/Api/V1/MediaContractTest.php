<?php

namespace Tests\Feature\Api\V1;

use App\Models\Media;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MediaContractTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    public function test_authenticated_native_client_uploads_media_with_server_identity_and_no_raw_path(): void
    {
        $user = $this->member('upload');
        [$token, $deviceId] = $this->nativeSession($user);
        $file = UploadedFile::fake()->image('photo.jpg', 20, 20);

        $response = $this->freshBearer($token, $deviceId)
            ->withHeader('Idempotency-Key', 'media-'.bin2hex(random_bytes(8)))
            ->post('/api/v1/media', [
                'purpose' => 'group.post',
                'file' => $file,
            ], ['Accept' => 'application/json'])
            ->assertCreated()
            ->assertJsonStructure(['data' => ['id', 'purpose', 'mime_type', 'size', 'sha256', 'status']])
            ->assertJsonPath('data.purpose', 'group.post');

        $this->assertNull(data_get($response->json(), 'data.path'));
        $this->assertNull(data_get($response->json(), 'data.storage_key'));
        $this->assertDatabaseHas('media', [
            'public_id' => $response->json('data.id'),
            'uploader_user_id' => $user->id,
            'purpose' => 'group.post',
        ]);
    }

    public function test_profile_avatar_rejects_deceptive_or_non_image_mime_and_unsupported_purpose(): void
    {
        $user = $this->member('policy');
        [$token, $deviceId] = $this->nativeSession($user);

        $this->freshBearer($token, $deviceId)
            ->withHeader('Idempotency-Key', 'media-bad-mime-'.bin2hex(random_bytes(8)))
            ->post('/api/v1/media', [
                'purpose' => 'profile.avatar',
                'file' => UploadedFile::fake()->create('avatar.jpg', 10, 'application/pdf'),
            ], ['Accept' => 'application/json'])
            ->assertStatus(422);

        $this->freshBearer($token, $deviceId)
            ->withHeader('Idempotency-Key', 'media-bad-purpose-'.bin2hex(random_bytes(8)))
            ->post('/api/v1/media', [
                'purpose' => 'arbitrary.upload',
                'file' => UploadedFile::fake()->image('image.jpg'),
            ], ['Accept' => 'application/json'])
            ->assertStatus(422);
    }

    public function test_media_record_is_owned_by_uploader_and_internal_storage_identity_is_not_public_identity(): void
    {
        $user = $this->member('ownership');
        [$token, $deviceId] = $this->nativeSession($user);
        $pdf = UploadedFile::fake()->createWithContent(
            'evidence.pdf',
            "%PDF-1.4\n1 0 obj\n<< /Type /Catalog >>\nendobj\ntrailer\n<<>>\n%%EOF\n",
        );

        $response = $this->freshBearer($token, $deviceId)
            ->withHeader('Idempotency-Key', 'media-owner-'.bin2hex(random_bytes(8)))
            ->post('/api/v1/media', [
                'purpose' => 'project.attachment',
                'file' => $pdf,
            ], ['Accept' => 'application/json'])
            ->assertCreated();

        $media = Media::query()->where('public_id', $response->json('data.id'))->firstOrFail();
        $this->assertSame($user->id, $media->uploader_user_id);
        $this->assertNotSame($media->storage_key, $media->public_id);
        $this->assertSame(hash_file('sha256', $media->storagePath()), $media->sha256);
    }

    private function member(string $suffix): User
    {
        return User::factory()->create([
            'email' => 'm5-media-'.$suffix.'-'.bin2hex(random_bytes(4)).'@example.test',
            'password' => Hash::make('secret-password'),
            'is_system' => false,
            'status' => 'active',
            'email_verified_at' => now(),
        ]);
    }

    private function nativeSession(User $user): array
    {
        $response = $this->postJson('/api/v1/auth/session', [
            'email' => $user->email,
            'password' => 'secret-password',
            'platform' => 'android',
            'app_version' => '1.0.0',
            'locale' => 'fa',
            'timezone' => 'Asia/Tehran',
            'push_capable' => true,
        ])->assertCreated();

        return [$response->json('data.token'), $response->json('data.device.id')];
    }

    private function freshBearer(string $token, string $deviceId): self
    {
        Auth::forgetGuards();

        return $this->withToken($token)->withHeader('X-Device-ID', $deviceId);
    }
}
