<?php

namespace Tests\Unit\Services\Push;

use App\Models\NativeDevice;
use App\Services\Push\FcmAccessTokenProvider;
use App\Services\Push\FcmHttpV1PushDeliveryGateway;
use App\Services\Push\PushDeliveryResult;
use App\Services\Push\PushEnvelope;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class FcmHttpV1PushDeliveryGatewayTest extends TestCase
{
    public function test_sends_http_v1_message_with_bearer_token_and_device_token(): void
    {
        Http::fake([
            'https://fcm.googleapis.com/v1/projects/project-123/messages:send' => Http::response([
                'name' => 'projects/project-123/messages/message-1',
            ], 200),
        ]);
        $gateway = new FcmHttpV1PushDeliveryGateway($this->tokenProvider('access-secret'), 'project-123');

        $result = $gateway->send($this->device('fcm-device-token'), $this->envelope());

        $this->assertTrue($result->delivered());
        Http::assertSent(function ($request) {
            $body = $request->data();

            return $request->url() === 'https://fcm.googleapis.com/v1/projects/project-123/messages:send'
                && $request->hasHeader('Authorization', 'Bearer access-secret')
                && data_get($body, 'message.token') === 'fcm-device-token'
                && data_get($body, 'message.notification.title') === 'Title'
                && data_get($body, 'message.notification.body') === 'Body'
                && data_get($body, 'message.data.type') === 'group.notice';
        });
    }

    public function test_missing_credentials_fail_safe_without_http_request(): void
    {
        Http::fake();
        $gateway = new FcmHttpV1PushDeliveryGateway($this->tokenProvider(null), 'project-123');

        $result = $gateway->send($this->device('device-token'), $this->envelope());

        $this->assertSame(PushDeliveryResult::TEMPORARY_FAILURE, $result->status);
        $this->assertSame('fcm_credentials_missing', $result->code);
        Http::assertNothingSent();
    }

    public function test_unregistered_token_is_normalized_as_invalid_token(): void
    {
        Http::fake([
            '*' => Http::response([
                'error' => [
                    'status' => 'NOT_FOUND',
                    'details' => [['errorCode' => 'UNREGISTERED']],
                ],
            ], 404),
        ]);
        $gateway = new FcmHttpV1PushDeliveryGateway($this->tokenProvider('access'), 'project-123');

        $result = $gateway->send($this->device('expired-token'), $this->envelope());

        $this->assertSame(PushDeliveryResult::INVALID_TOKEN, $result->status);
        $this->assertSame('UNREGISTERED', $result->code);
    }

    public function test_rate_limit_and_server_failures_are_retryable(): void
    {
        foreach ([429, 500, 503] as $status) {
            Http::fake(['*' => Http::response([], $status)]);
            $gateway = new FcmHttpV1PushDeliveryGateway($this->tokenProvider('access'), 'project-123');

            $result = $gateway->send($this->device('device-token'), $this->envelope());

            $this->assertSame(PushDeliveryResult::TEMPORARY_FAILURE, $result->status);
        }
    }

    public function test_other_provider_4xx_is_permanent_failure(): void
    {
        Http::fake(['*' => Http::response(['error' => ['status' => 'PERMISSION_DENIED']], 403)]);
        $gateway = new FcmHttpV1PushDeliveryGateway($this->tokenProvider('access'), 'project-123');

        $result = $gateway->send($this->device('device-token'), $this->envelope());

        $this->assertSame(PushDeliveryResult::PERMANENT_FAILURE, $result->status);
        $this->assertSame('PERMISSION_DENIED', $result->code);
    }

    private function tokenProvider(?string $token): FcmAccessTokenProvider
    {
        return new class($token) extends FcmAccessTokenProvider {
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
            'push_provider' => 'fcm',
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
