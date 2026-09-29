<?php

namespace App\Services\Push;

use App\Models\NativeDevice;

final readonly class CompositePushDeliveryGateway implements PushDeliveryGateway
{
    public function __construct(
        private PushDeliveryGateway $fcm,
        private PushDeliveryGateway $hms,
    ) {
    }

    public function send(NativeDevice $device, PushEnvelope $envelope): PushDeliveryResult
    {
        return match ($device->push_provider) {
            'fcm' => $this->fcm->send($device, $envelope),
            'hms' => $this->hms->send($device, $envelope),
            default => PushDeliveryResult::permanentFailure('unsupported_provider'),
        };
    }
}
