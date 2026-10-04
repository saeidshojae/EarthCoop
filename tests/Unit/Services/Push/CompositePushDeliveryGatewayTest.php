<?php

namespace Tests\Unit\Services\Push;

use App\Models\NativeDevice;
use App\Services\Push\CompositePushDeliveryGateway;
use App\Services\Push\PushDeliveryGateway;
use App\Services\Push\PushDeliveryResult;
use App\Services\Push\PushEnvelope;
use PHPUnit\Framework\TestCase;

class CompositePushDeliveryGatewayTest extends TestCase
{
    public function test_dispatches_fcm_and_hms_to_the_matching_gateway(): void
    {
        $fcm = new RecordingGateway(PushDeliveryResult::success());
        $hms = new RecordingGateway(PushDeliveryResult::success());
        $gateway = new CompositePushDeliveryGateway($fcm, $hms);
        $envelope = new PushEnvelope('Title', 'Body', 'info', null);

        $fcmDevice = new NativeDevice(['push_provider' => 'fcm']);
        $hmsDevice = new NativeDevice(['push_provider' => 'hms']);

        $this->assertTrue($gateway->send($fcmDevice, $envelope)->delivered());
        $this->assertTrue($gateway->send($hmsDevice, $envelope)->delivered());
        $this->assertSame(1, $fcm->calls);
        $this->assertSame(1, $hms->calls);
    }

    public function test_unknown_provider_fails_safe_without_dispatching(): void
    {
        $fcm = new RecordingGateway(PushDeliveryResult::success());
        $hms = new RecordingGateway(PushDeliveryResult::success());
        $gateway = new CompositePushDeliveryGateway($fcm, $hms);

        $result = $gateway->send(
            new NativeDevice(['push_provider' => 'unknown']),
            new PushEnvelope('Title', 'Body', 'info', null),
        );

        $this->assertSame(PushDeliveryResult::PERMANENT_FAILURE, $result->status);
        $this->assertSame('unsupported_provider', $result->code);
        $this->assertSame(0, $fcm->calls);
        $this->assertSame(0, $hms->calls);
    }
}

class RecordingGateway implements PushDeliveryGateway
{
    public int $calls = 0;

    public function __construct(private readonly PushDeliveryResult $result)
    {
    }

    public function send(NativeDevice $device, PushEnvelope $envelope): PushDeliveryResult
    {
        $this->calls++;

        return $this->result;
    }
}
