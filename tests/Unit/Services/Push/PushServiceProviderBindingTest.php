<?php

namespace Tests\Unit\Services\Push;

use App\Models\NativeDevice;
use App\Services\Push\CompositePushDeliveryGateway;
use App\Services\Push\NullPushDeliveryGateway;
use App\Services\Push\PushDeliveryGateway;
use App\Services\Push\PushEnvelope;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PushServiceProviderBindingTest extends TestCase
{
    public function test_default_driver_remains_disabled(): void
    {
        config(['services.push.driver' => 'null']);
        $this->app->forgetInstance(PushDeliveryGateway::class);
        $this->assertInstanceOf(NullPushDeliveryGateway::class, app(PushDeliveryGateway::class));
    }

    public function test_configured_providers_without_credentials_fail_without_network_requests(): void
    {
        Http::fake();
        config([
            'services.push.driver' => 'providers',
            'services.push.fcm.credentials' => null,
            'services.push.fcm.project_id' => null,
            'services.push.hms.credentials' => null,
            'services.push.hms.client_id' => null,
        ]);
        $this->app->forgetInstance(PushDeliveryGateway::class);
        $gateway = app(PushDeliveryGateway::class);
        $this->assertInstanceOf(CompositePushDeliveryGateway::class, $gateway);
        foreach (['fcm', 'hms'] as $provider) {
            $result = $gateway->send(
                new NativeDevice(['push_provider' => $provider]),
                new PushEnvelope('Title', 'Body', 'info', null),
            );
            $this->assertFalse($result->delivered());
        }
        Http::assertNothingSent();
    }

    public function test_google_auth_dependency_is_available(): void
    {
        $this->assertTrue(class_exists(\Google\Auth\Credentials\ServiceAccountCredentials::class));
    }
}
