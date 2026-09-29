<?php

namespace Tests\Feature\Api\V1;

use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class DevicePushContractTest extends TestCase
{
    private bool $createdUsersTable = false;
    private bool $createdNativeDevicesTable = false;
    private bool $createdPersonalAccessTokensTable = false;
    private array $createdUserIds = [];

    protected function setUp(): void
    {
        parent::setUp();

        if (! Schema::hasTable('users')) {
            $this->createdUsersTable = true;
            Schema::create('users', function (Blueprint $table) {
                $table->id();
                $table->string('email')->unique()->nullable();
                $table->string('password');
                $table->boolean('is_system')->default(false);
                $table->string('status')->default('active');
                $table->string('national_id')->nullable();
                $table->string('first_name')->nullable();
                $table->string('last_name')->nullable();
                $table->string('last_login_ip')->nullable();
                $table->timestamp('last_login_at')->nullable();
                $table->rememberToken();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('native_devices')) {
            $this->createdNativeDevicesTable = true;
            Schema::create('native_devices', function (Blueprint $table) {
                $table->id();
                $table->uuid('public_id')->unique();
                $table->unsignedBigInteger('user_id');
                $table->string('platform', 20);
                $table->string('app_version', 50);
                $table->string('locale', 8);
                $table->string('timezone', 100)->nullable();
                $table->boolean('push_capable')->default(false);
                $table->timestamp('last_seen_at')->nullable();
                $table->timestamp('revoked_at')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('personal_access_tokens')) {
            $this->createdPersonalAccessTokensTable = true;
            Schema::create('personal_access_tokens', function (Blueprint $table) {
                $table->id();
                $table->morphs('tokenable');
                $table->string('name');
                $table->string('token', 64)->unique();
                $table->text('abilities')->nullable();
                $table->unsignedBigInteger('native_device_id')->nullable()->index();
                $table->timestamp('last_used_at')->nullable();
                $table->timestamp('expires_at')->nullable();
                $table->timestamps();
            });
        } elseif (! Schema::hasColumn('personal_access_tokens', 'native_device_id')) {
            Schema::table('personal_access_tokens', function (Blueprint $table) {
                $table->unsignedBigInteger('native_device_id')->nullable()->index();
            });
        }
    }

    protected function tearDown(): void
    {
        try {
            Auth::forgetGuards();

            if ($this->createdPersonalAccessTokensTable) {
                Schema::dropIfExists('personal_access_tokens');
            } elseif ($this->createdUserIds !== [] && Schema::hasTable('personal_access_tokens')) {
                DB::table('personal_access_tokens')
                    ->where('tokenable_type', User::class)
                    ->whereIn('tokenable_id', $this->createdUserIds)
                    ->delete();
            }

            if ($this->createdNativeDevicesTable) {
                Schema::dropIfExists('native_devices');
            } elseif ($this->createdUserIds !== [] && Schema::hasTable('native_devices')) {
                DB::table('native_devices')->whereIn('user_id', $this->createdUserIds)->delete();
            }

            if ($this->createdUsersTable) {
                Schema::dropIfExists('users');
            } elseif ($this->createdUserIds !== [] && Schema::hasTable('users')) {
                DB::table('users')->whereIn('id', $this->createdUserIds)->delete();
            }
        } finally {
            parent::tearDown();
        }
    }

    public function test_current_device_can_register_rotate_and_disable_push_without_token_echo(): void
    {
        $user = $this->createUser('push-owner@example.test', 'secret-password');
        $session = $this->nativeSession($user, 'secret-password');
        $device = $session['device'];
        $token = $session['token'];

        $registered = $this->withFreshToken($token)
            ->withHeader('X-Device-ID', $device)
            ->putJson("/api/v1/devices/{$device}/push", [
                'provider' => 'fcm',
                'token' => 'push-token-one',
            ])
            ->assertOk()
            ->assertJsonPath('data.device_id', $device)
            ->assertJsonPath('data.provider', 'fcm')
            ->assertJsonMissing(['token' => 'push-token-one']);

        $this->assertNull(data_get($registered->json(), 'data.token'));

        $this->withFreshToken($token)
            ->withHeader('X-Device-ID', $device)
            ->putJson("/api/v1/devices/{$device}/push", [
                'provider' => 'apns',
                'token' => 'push-token-two',
            ])
            ->assertOk()
            ->assertJsonPath('data.provider', 'apns');

        $hms = $this->withFreshToken($token)
            ->withHeader('X-Device-ID', $device)
            ->putJson("/api/v1/devices/{$device}/push", [
                'provider' => 'hms',
                'token' => 'huawei-push-token-three',
            ])
            ->assertOk()
            ->assertJsonPath('data.provider', 'hms')
            ->assertJsonMissing(['token' => 'huawei-push-token-three']);

        $this->assertNull(data_get($hms->json(), 'data.token'));

        $this->withFreshToken($token)
            ->withHeader('X-Device-ID', $device)
            ->putJson("/api/v1/devices/{$device}/push", [
                'provider' => 'unknown-provider',
                'token' => 'must-never-register',
            ])
            ->assertStatus(422);

        $this->withFreshToken($token)
            ->withHeader('X-Device-ID', $device)
            ->deleteJson("/api/v1/devices/{$device}/push")
            ->assertOk()
            ->assertJsonPath('data.push_enabled', false);
    }

    public function test_push_registration_fails_closed_for_non_current_or_other_users_device(): void
    {
        $owner = $this->createUser('device-owner@example.test', 'owner-password');
        $other = $this->createUser('other-user@example.test', 'other-password');

        $owned = $this->nativeSession($owner, 'owner-password');
        $otherSession = $this->nativeSession($other, 'other-password');

        $this->withFreshToken($otherSession['token'])
            ->withHeader('X-Device-ID', $otherSession['device'])
            ->putJson("/api/v1/devices/{$owned['device']}/push", [
                'provider' => 'fcm',
                'token' => 'stolen-token-attempt',
            ])
            ->assertStatus(404);
    }

    public function test_one_provider_token_cannot_remain_active_for_two_device_owners(): void
    {
        $first = $this->createUser('first-push@example.test', 'first-password');
        $second = $this->createUser('second-push@example.test', 'second-password');
        $a = $this->nativeSession($first, 'first-password');
        $b = $this->nativeSession($second, 'second-password');

        $this->withFreshToken($a['token'])
            ->withHeader('X-Device-ID', $a['device'])
            ->putJson("/api/v1/devices/{$a['device']}/push", [
                'provider' => 'fcm',
                'token' => 'shared-provider-token',
            ])
            ->assertOk();

        $this->withFreshToken($b['token'])
            ->withHeader('X-Device-ID', $b['device'])
            ->putJson("/api/v1/devices/{$b['device']}/push", [
                'provider' => 'fcm',
                'token' => 'shared-provider-token',
            ])
            ->assertStatus(409)
            ->assertJsonPath('error.code', 'push_token_in_use');
    }

    private function nativeSession(User $user, string $password): array
    {
        $response = $this->postJson('/api/v1/auth/session', [
            'email' => $user->email,
            'password' => $password,
            'platform' => 'android',
            'app_version' => '1.0.0',
            'locale' => 'fa',
            'timezone' => 'Asia/Tehran',
            'push_capable' => true,
        ])->assertCreated();

        return [
            'token' => (string) $response->json('data.token'),
            'device' => (string) $response->json('data.device.id'),
        ];
    }

    private function withFreshToken(string $token): self
    {
        Auth::forgetGuards();

        return $this->withToken($token);
    }

    private function createUser(string $email, string $password): User
    {
        $id = DB::table('users')->insertGetId([
            'email' => $email,
            'password' => Hash::make($password),
            'is_system' => false,
            'status' => 'active',
            'first_name' => 'Test',
            'last_name' => 'Member',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $this->createdUserIds[] = (int) $id;

        return User::query()->findOrFail($id);
    }
}
