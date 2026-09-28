<?php

namespace Tests\Unit\Push;

use App\Models\NativeDevice;
use App\Services\Push\NullPushDeliveryGateway;
use App\Services\Push\PushDeliveryResult;
use App\Services\Push\PushEnvelope;
use PHPUnit\Framework\TestCase;

class PushDeliveryGatewayTest extends TestCase
{
    public function test_safe_preview_envelope_is_provider_neutral(): void
    {
        $envelope = new PushEnvelope(
            title: 'Project updated',
            body: 'Open EarthCoop to view the update.',
            type: 'project.status_changed',
            link: [
                'version' => 1,
                'route' => 'project.detail',
                'params' => ['project_id' => '123'],
            ],
            context: ['notification_id' => 'n-1'],
        );

        $this->assertSame('Project updated', $envelope->title);
        $this->assertSame('project.status_changed', $envelope->type);
        $this->assertArrayNotHasKey('provider_token', $envelope->toArray());
        $this->assertSame('project.detail', $envelope->toArray()['link']['route']);
    }

    public function test_null_gateway_returns_delivered_result_without_provider_sdk_dependency(): void
    {
        $device = new NativeDevice([
            'public_id' => '00000000-0000-4000-8000-000000000001',
            'push_provider' => 'fcm',
        ]);

        $result = (new NullPushDeliveryGateway())->send(
            $device,
            new PushEnvelope('Title', 'Body', 'info', null, []),
        );

        $this->assertTrue($result->delivered());
        $this->assertSame(PushDeliveryResult::DELIVERED, $result->status);
    }

    public function test_delivery_result_has_stable_failure_taxonomy(): void
    {
        $temporary = PushDeliveryResult::temporaryFailure('provider_unavailable');
        $invalid = PushDeliveryResult::invalidToken('unregistered');

        $this->assertSame(PushDeliveryResult::TEMPORARY_FAILURE, $temporary->status);
        $this->assertFalse($temporary->delivered());
        $this->assertSame(PushDeliveryResult::INVALID_TOKEN, $invalid->status);
        $this->assertFalse($invalid->delivered());
    }
}
