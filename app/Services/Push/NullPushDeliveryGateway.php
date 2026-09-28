<?php

namespace App\Services\Push;

use App\Models\NativeDevice;

class NullPushDeliveryGateway implements PushDeliveryGateway
{
    public function send(NativeDevice $device, PushEnvelope $envelope): PushDeliveryResult
    {
        return PushDeliveryResult::temporaryFailure('provider_not_configured');
    }
}
