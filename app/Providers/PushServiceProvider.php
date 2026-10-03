<?php

namespace App\Providers;

use App\Services\Push\CompositePushDeliveryGateway;
use App\Services\Push\FcmAccessTokenProvider;
use App\Services\Push\FcmHttpV1PushDeliveryGateway;
use App\Services\Push\HmsAccessTokenProvider;
use App\Services\Push\HmsPushDeliveryGateway;
use App\Services\Push\NullPushDeliveryGateway;
use App\Services\Push\PushDeliveryGateway;
use Illuminate\Support\ServiceProvider;

class PushServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(PushDeliveryGateway::class, function () {
            if (config('services.push.driver', 'null') !== 'providers') {
                return new NullPushDeliveryGateway();
            }

            $fcm = new FcmHttpV1PushDeliveryGateway(
                new FcmAccessTokenProvider(config('services.push.fcm.credentials')),
                config('services.push.fcm.project_id'),
            );
            $hms = new HmsPushDeliveryGateway(
                new HmsAccessTokenProvider(config('services.push.hms.credentials')),
                config('services.push.hms.project_id'),
            );

            return new CompositePushDeliveryGateway($fcm, $hms);
        });
    }
}
