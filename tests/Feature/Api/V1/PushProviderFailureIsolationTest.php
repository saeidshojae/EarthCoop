<?php

namespace Tests\Feature\Api\V1;

use App\Models\NativeDevice;
use App\Models\User;
use App\Services\Push\PushDeliveryGateway;
use App\Services\Push\PushEnvelope;
use App\Services\Push\PushNotificationDispatcher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Tests\TestCase;

class PushProviderFailureIsolationTest extends TestCase
{
    use RefreshDatabase;

    public function test_provider_exception_is_recorded_as_retryable_failure_and_does_not_escape_dispatcher(): void
    {
        $user = User::factory()->create();
        $device = NativeDevice::query()->create([
            'public_id' => '11111111-1111-4111-8111-111111111111',
            'user_id' => $user->id,
            'platform' => 'android',
            'app_version' => '1.0.0',
            'locale' => 'fa',
            'timezone' => 'Asia/Tehran',
            'push_capable' => true,
            'push_provider' => 'fcm',
            'push_token' => Crypt::encryptString('provider-token'),
            'push_token_hash' => hash('sha256', 'provider-token'),
            'push_enabled_at' => now(),
            'push_disabled_at' => null,
        ]);

        $this->app->instance(PushDeliveryGateway::class, new class implements PushDeliveryGateway {
            public function send(NativeDevice $device, PushEnvelope $envelope): \App\Services\Push\PushDeliveryResult
            {
                throw new \RuntimeException('provider network unavailable');
            }
        });

        app(PushNotificationDispatcher::class)->dispatch($user, [
            'title' => 'Safe preview',
            'message' => 'Open EarthCoop.',
            'type' => 'info',
            'url' => '/home',
            'context' => [],
        ]);

        $device->refresh();
        $this->assertNotNull($device->last_push_failure_at);
        $this->assertSame('provider_exception', $device->last_push_failure_code);
        $this->assertTrue($device->push_capable);
        $this->assertNull($device->push_disabled_at);
    }
}
