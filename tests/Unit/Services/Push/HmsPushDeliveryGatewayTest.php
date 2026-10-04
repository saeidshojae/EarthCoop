<?php

namespace Tests\Unit\Services\Push;

use App\Models\NativeDevice;
use App\Services\Push\HmsAccessTokenProvider;
use App\Services\Push\HmsPushDeliveryGateway;
use App\Services\Push\PushDeliveryResult;
use App\Services\Push\PushEnvelope;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class HmsPushDeliveryGatewayTest extends TestCase
{
    public function test_sends_android_v1_notification_with_oauth_and_device_token(): void
    {
        Http::fake([
            'https://push-api.cloud.huawei.com/v1/client-456/messages:send' => Http::response([
                'code' => '80000000',
                'msg' => 'Success',
            ], 200),
        ]);
        $gateway = new HmsPushDeliveryGateway($this->tokenProvider('oauth-token'), 'client-456');

        $result = $gateway->send($this->device('hms-device-token'), $this->envelope());

        $this->assertTrue($result->delivered());
        Http::assertSent(function ($request) {
            $body = $request->data();

            return $request->url() === 'https://push-api.cloud.huawei.com/v1/client-456/messages:send'
                && $request->hasHeader('Authorization', 'Bearer oauth-token')
                && ! $request->hasHeader('push-type')
                && data_get($body, 'message.token.0') === 'hms-device-token'
                && data_get($body, 'message.notification.title') === 'Title'
                && data_get($body, 'message.notification.body') === 'Body';
        });
    }

    public function test_missing_credentials_fail_safe_without_http_request(): void
    {
        Http::fake();
        $gateway = new HmsPushDeliveryGateway($this->tokenProvider(null), 'client-456');

        $result = $gateway->send($this->device('device-token'), $this->envelope());

        $this->assertSame(PushDeliveryResult::TEMPORARY_FAILURE, $result->status);
        $this->assertSame('hms_credentials_missing', $result->code);
        Http::assertNothingSent();
    }

    public function test_all_tokens_invalid_is_normalized_as_invalid_token(): void
    {
        Http::fake(['*' => Http::response(['code' => '80300007', 'msg' => 'All tokens invalid'], 400)]);
        $gateway = new HmsPushDeliveryGateway($this->tokenProvider('jwt'), 'client-456');

        $result = $gateway->send($this->device('expired-token'), $this->envelope());

        $this->assertSame(PushDeliveryResult::INVALID_TOKEN, $result->status);
        $this->assertSame('80300007', $result->code);
    }

    public function test_rate_limit_and_server_failures_are_retryable(): void
    {
        foreach ([429, 500, 503] as $status) {
            Http::fake(['*' => Http::response([], $status)]);
            $gateway = new HmsPushDeliveryGateway($this->tokenProvider('jwt'), 'client-456');

            $result = $gateway->send($this->device('device-token'), $this->envelope());

            $this->assertSame(PushDeliveryResult::TEMPORARY_FAILURE, $result->status);
        }
    }

    public function test_other_provider_4xx_is_permanent_failure(): void
    {
        Http::fake(['*' => Http::response(['code' => '80100001', 'msg' => 'Invalid request'], 400)]);
        $gateway = new HmsPushDeliveryGateway($this->tokenProvider('jwt'), 'client-456');

        $result = $gateway->send($this->device('device-token'), $this->envelope());

        $this->assertSame(PushDeliveryResult::PERMANENT_FAILURE, $result->status);
        $this->assertSame('80100001', $result->code);
    }

    private function tokenProvider(?string $token): HmsAccessTokenProvider
    {
        return new class($token) extends HmsAccessTokenProvider {
            public function __construct(private readonly ?string $fakeToken)
            {
            }

            public function token(): ?string
            {
                return $this->fakeToken;
            }
        };
    }

    private function device(string $token): NativeDevice
    {
        return new NativeDevice([
            'push_provider' => 'hms',
            'push_token' => Crypt::encryptString($token),
        ]);
    }

    private function envelope(): PushEnvelope
    {
        return new PushEnvelope(
            'Title',
            'Body',
            'group.notice',
            ['version' => 1, 'route' => 'group.detail', 'params' => ['group_id' => 42]],
            ['notification_id' => 'n-1'],
        );
    }
}
