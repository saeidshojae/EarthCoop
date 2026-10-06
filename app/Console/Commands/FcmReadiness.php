<?php

namespace App\Console\Commands;

use App\Services\Push\FcmAccessTokenProvider;
use App\Services\Push\FcmReadinessCheck;
use Illuminate\Console\Command;

class FcmReadiness extends Command
{
    protected $signature = 'deployment:fcm-readiness';

    protected $description = 'Validate FCM provider configuration without delivering a notification';

    public function handle(): int
    {
        $path = config('services.push.fcm.credentials');
        $project = config('services.push.fcm.project_id');
        $result = (new FcmReadinessCheck(
            new FcmAccessTokenProvider(is_string($path) ? $path : null),
            is_string($project) ? $project : null,
            is_string($path) ? $path : null,
        ))->check();
        $this->line(json_encode($result, JSON_THROW_ON_ERROR));

        return $result['ready'] ? self::SUCCESS : self::FAILURE;
    }
}
