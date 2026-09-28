<?php

namespace App\Services\Push;

use App\Models\NativeDevice;

interface PushDeliveryGateway
{
    public function send(NativeDevice $device, PushEnvelope $envelope): PushDeliveryResult;
}
