<?php

namespace Tests\Feature\Api\V1;

use App\Models\NativeDevice;
use App\Models\User;
use App\Services\NotificationService;
use App\Services\Push\PushDeliveryGateway;
use App\Services\Push\PushDeliveryResult;
use App\Services\Push\PushEnvelope;
use App\Support\Notifications\NotificationLink;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class M5NativeDeliveryAcceptanceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'queue.default' => 'sync',
            'broadcasting.default' => 'null',
            'client-compatibility.android.minimum_version' => '1.2.0',
            'client-compatibility.android.latest_version' => '1.4.0',
        ]);
    }

    public function test_native_delivery_foundation_survives_push_revocation_recovery_replay_and_version_gate(): void
    {
        $user = User::factory()->create([
            'email' => 'm5-acceptance-'.bin2hex(random_bytes(4)).'@example.test',
            'password' => Hash::make('secret-password'),
            'is_system' => false,
            'status' => 'active',
            'email_verified_at' => now(),
        ]);
        [$token, $deviceId] = $this->nativeSession($user);

        $gateway = new class implements PushDeliveryGateway {
            public int $calls = 0;

            public function send(NativeDevice $device, PushEnvelope $envelope): PushDeliveryResult
            {
                $this->calls++;

                return PushDeliveryResult::success();
            }
        };
        $this->app->instance(PushDeliveryGateway::class, $gateway);

        $this->bearer($token, $deviceId)
            ->putJson('/api/v1/devices/'.$deviceId.'/push', [
                'provider' => 'fcm',
                'token' => 'm5-acceptance-provider-token',
            ])
            ->assertOk();

        app(NotificationService::class)->notifyUser(
            $user,
            'Project changed',
            'Open EarthCoop to review it.',
            '/projects/42',
            'project.status_changed',
            [],
            new NotificationLink(1, 'project.detail', ['project_id' => '42'], 'https://earthcoop.ir/projects/42'),
        );
        $this->assertSame(1, $gateway->calls);
        $this->assertSame(1, $user->notifications()->count());

        $this->bearer($token, $deviceId)
            ->getJson('/api/v1/notifications?page[limit]=1')
            ->assertOk()
            ->assertJsonPath('data.0.link.route', 'project.detail');

        $this->bearer($token, $deviceId)
            ->deleteJson('/api/v1/auth/session')
            ->assertNoContent();

        app(NotificationService::class)->notifyUser(
            $user,
            'After revoke',
            'Canonical notification still exists.',
            '/home',
            'info',
        );
        $this->assertSame(1, $gateway->calls);
        $this->assertSame(2, $user->notifications()->count());

        [$recoveryToken, $recoveryDevice] = $this->nativeSession($user);
        $page = $this->bearer($recoveryToken, $recoveryDevice)
            ->getJson('/api/v1/notifications?page[limit]=1')
            ->assertOk()
            ->assertJsonPath('meta.pagination.has_more', true);
        $cursor = (string) $page->json('meta.pagination.next_cursor');

        $this->bearer($recoveryToken, $recoveryDevice)
            ->getJson('/api/v1/notifications?page[limit]=1&page[cursor]='.urlencode($cursor))
            ->assertOk()
            ->assertJsonPath('meta.pagination.has_more', false);

        $replayKey = 'm5-acceptance-preferences';
        $this->bearer($recoveryToken, $recoveryDevice)
            ->withHeader('Idempotency-Key', $replayKey)
            ->patchJson('/api/v1/notifications/preferences', ['push_notifications' => false])
            ->assertOk()
            ->assertJsonPath('data.push_notifications', false);
        $this->bearer($recoveryToken, $recoveryDevice)
            ->withHeader('Idempotency-Key', $replayKey)
            ->patchJson('/api/v1/notifications/preferences', ['push_notifications' => false])
            ->assertOk()
            ->assertHeader('Idempotency-Replayed', 'true');

        $this->getJson('/api/v1/bootstrap?platform=android&version=1.0.0')
            ->assertOk()
            ->assertJsonPath('data.client.update_required', true);
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
