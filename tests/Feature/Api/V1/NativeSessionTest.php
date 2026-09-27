<?php

namespace Tests\Feature\Api\V1;

use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class NativeSessionTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (! Schema::hasTable('users')) {
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
        Schema::dropIfExists('personal_access_tokens');
        Schema::dropIfExists('native_devices');
        Schema::dropIfExists('users');
        parent::tearDown();
    }

    public function test_valid_credentials_create_owned_device_and_finite_native_token(): void
    {
        $user = $this->createUser('member@example.test', 'secret-password');

        $response = $this->postJson('/api/v1/auth/session', $this->loginPayload($user->email, 'secret-password'));

        $response
            ->assertCreated()
            ->assertJsonPath('data.token_type', 'Bearer')
            ->assertJsonPath('data.user.id', $user->id)
            ->assertJsonPath('data.device.platform', 'android')
            ->assertJsonPath('data.device.locale', 'fa')
            ->assertJsonStructure(['data' => ['token', 'expires_at', 'device' => ['id']]]);

        $deviceId = DB::table('native_devices')->where('user_id', $user->id)->value('id');
        $this->assertNotNull($deviceId);

        $token = DB::table('personal_access_tokens')->where('tokenable_id', $user->id)->first();
        $this->assertNotNull($token);
        $this->assertSame($deviceId, (int) $token->native_device_id);
        $this->assertNotNull($token->expires_at);
        $this->assertContains('native', json_decode($token->abilities, true));
    }

    public function test_wrong_credentials_return_generic_invalid_credentials(): void
    {
        $this->createUser('member@example.test', 'correct-password');

        $this->postJson('/api/v1/auth/session', $this->loginPayload('member@example.test', 'wrong-password'))
            ->assertStatus(401)
            ->assertJsonPath('error.code', 'invalid_credentials');

        $this->assertSame(0, DB::table('native_devices')->count());
        $this->assertSame(0, DB::table('personal_access_tokens')->count());
    }

    public function test_system_identity_cannot_obtain_interactive_native_session(): void
    {
        $user = $this->createUser('system@example.test', 'secret-password', true);

        $this->postJson('/api/v1/auth/session', $this->loginPayload($user->email, 'secret-password'))
            ->assertStatus(401)
            ->assertJsonPath('error.code', 'invalid_credentials');

        $this->assertSame(0, DB::table('native_devices')->count());
        $this->assertSame(0, DB::table('personal_access_tokens')->count());
    }

    public function test_user_cannot_attach_device_owned_by_another_user(): void
    {
        $owner = $this->createUser('owner@example.test', 'owner-password');
        $other = $this->createUser('other@example.test', 'other-password');

        $owned = $this->postJson('/api/v1/auth/session', $this->loginPayload($owner->email, 'owner-password'))
            ->assertCreated();

        $devicePublicId = $owned->json('data.device.id');

        $this->postJson('/api/v1/auth/session', array_merge(
            $this->loginPayload($other->email, 'other-password'),
            ['device_id' => $devicePublicId],
        ))
            ->assertStatus(403)
            ->assertJsonPath('error.code', 'device_not_owned');

        $this->assertSame(1, DB::table('native_devices')->count());
        $this->assertSame(1, DB::table('personal_access_tokens')->count());
    }

    public function test_token_for_device_a_cannot_claim_device_b(): void
    {
        $user = $this->createUser('member@example.test', 'secret-password');
        $a = $this->postJson('/api/v1/auth/session', $this->loginPayload($user->email, 'secret-password'))->assertCreated();
        $b = $this->postJson('/api/v1/auth/session', $this->loginPayload($user->email, 'secret-password'))->assertCreated();

        $this->withFreshToken($a->json('data.token'))
            ->withHeader('X-Device-ID', $b->json('data.device.id'))
            ->getJson('/api/v1/auth/session')
            ->assertStatus(403)
            ->assertJsonPath('error.code', 'device_mismatch');
    }

    public function test_rotation_revokes_only_current_device_token_and_keeps_other_device_valid(): void
    {
        $user = $this->createUser('member@example.test', 'secret-password');
        $a = $this->postJson('/api/v1/auth/session', $this->loginPayload($user->email, 'secret-password'))->assertCreated();
        $b = $this->postJson('/api/v1/auth/session', $this->loginPayload($user->email, 'secret-password'))->assertCreated();

        $oldAToken = $a->json('data.token');
        $deviceA = $a->json('data.device.id');
        $tokenB = $b->json('data.token');

        $rotated = $this->withFreshToken($oldAToken)
            ->withHeader('X-Device-ID', $deviceA)
            ->postJson('/api/v1/auth/session/rotate')
            ->assertOk();

        $newAToken = $rotated->json('data.token');
        $this->assertNotSame($oldAToken, $newAToken);

        $this->withFreshToken($oldAToken)->getJson('/api/v1/auth/session')->assertStatus(401);
        $this->withFreshToken($newAToken)->withHeader('X-Device-ID', $deviceA)->getJson('/api/v1/auth/session')->assertOk();
        $this->withFreshToken($tokenB)->withHeader('X-Device-ID', $b->json('data.device.id'))->getJson('/api/v1/auth/session')->assertOk();
    }

    public function test_logout_revokes_only_current_session_and_marks_its_device_revoked(): void
    {
        $user = $this->createUser('member@example.test', 'secret-password');
        $a = $this->postJson('/api/v1/auth/session', $this->loginPayload($user->email, 'secret-password'))->assertCreated();
        $b = $this->postJson('/api/v1/auth/session', $this->loginPayload($user->email, 'secret-password'))->assertCreated();

        $this->withFreshToken($a->json('data.token'))
            ->withHeader('X-Device-ID', $a->json('data.device.id'))
            ->deleteJson('/api/v1/auth/session')
            ->assertNoContent();

        $this->withFreshToken($a->json('data.token'))->getJson('/api/v1/auth/session')->assertStatus(401);
        $this->withFreshToken($b->json('data.token'))->withHeader('X-Device-ID', $b->json('data.device.id'))->getJson('/api/v1/auth/session')->assertOk();

        $this->assertNotNull(
            DB::table('native_devices')->where('public_id', $a->json('data.device.id'))->value('revoked_at')
        );
    }

    public function test_expired_token_is_rejected(): void
    {
        $user = $this->createUser('member@example.test', 'secret-password');
        $session = $this->postJson('/api/v1/auth/session', $this->loginPayload($user->email, 'secret-password'))->assertCreated();

        DB::table('personal_access_tokens')
            ->where('native_device_id', DB::table('native_devices')->where('public_id', $session->json('data.device.id'))->value('id'))
            ->update(['expires_at' => now()->subMinute()]);

        $this->withFreshToken($session->json('data.token'))
            ->getJson('/api/v1/auth/session')
            ->assertStatus(401)
            ->assertJsonPath('error.code', 'unauthenticated');
    }

    public function test_existing_browser_login_session_remains_independent(): void
    {
        $user = $this->createUser('browser@example.test', 'browser-password');

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'browser-password',
        ]);

        $this->assertAuthenticatedAs($user);
        $this->assertSame(0, DB::table('personal_access_tokens')->count());
        $this->assertSame(0, DB::table('native_devices')->count());
    }

    private function withFreshToken(string $token): self
    {
        Auth::forgetGuards();

        return $this->withToken($token);
    }

    private function createUser(string $email, string $password, bool $system = false): User
    {
        $id = DB::table('users')->insertGetId([
            'email' => $email,
            'password' => Hash::make($password),
            'is_system' => $system,
            'status' => 'active',
            'first_name' => 'Test',
            'last_name' => 'Member',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return User::query()->findOrFail($id);
    }

    private function loginPayload(string $email, string $password): array
    {
        return [
            'email' => $email,
            'password' => $password,
            'platform' => 'android',
            'app_version' => '1.0.0',
            'locale' => 'fa',
            'timezone' => 'Asia/Tehran',
            'push_capable' => true,
        ];
    }
}
