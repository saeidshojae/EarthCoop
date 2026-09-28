<?php

namespace Tests\Feature\Api\V1;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class DevicePushReleaseTest extends TestCase
{
    use RefreshDatabase;

    public function test_disabled_provider_token_can_be_registered_by_a_new_current_device(): void
    {
        $first = $this->member('first');
        $second = $this->member('second');
        [$tokenA, $deviceA] = $this->nativeSession($first);
        [$tokenB, $deviceB] = $this->nativeSession($second);

        $this->bearer($tokenA, $deviceA)
            ->putJson('/api/v1/devices/'.$deviceA.'/push', [
                'provider' => 'fcm',
                'token' => 'released-provider-token',
            ])
            ->assertOk();

        $this->bearer($tokenA, $deviceA)
            ->deleteJson('/api/v1/devices/'.$deviceA.'/push')
            ->assertOk();

        $this->bearer($tokenB, $deviceB)
            ->putJson('/api/v1/devices/'.$deviceB.'/push', [
                'provider' => 'fcm',
                'token' => 'released-provider-token',
            ])
            ->assertOk()
            ->assertJsonPath('data.provider', 'fcm');
    }

    private function member(string $suffix): User
    {
        return User::factory()->create([
            'email' => 'm5-push-release-'.$suffix.'-'.bin2hex(random_bytes(4)).'@example.test',
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

        return [(string) $response->json('data.token'), (string) $response->json('data.device.id')];
    }

    private function bearer(string $token, string $deviceId): self
    {
        Auth::forgetGuards();

        return $this->withToken($token)->withHeader('X-Device-ID', $deviceId);
    }
}
