<?php

namespace App\Providers;

use App\Services\Push\NullPushDeliveryGateway;
use App\Services\Push\PushDeliveryGateway;
use Illuminate\Support\ServiceProvider;

class PushServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(PushDeliveryGateway::class, NullPushDeliveryGateway::class);
    }
}
