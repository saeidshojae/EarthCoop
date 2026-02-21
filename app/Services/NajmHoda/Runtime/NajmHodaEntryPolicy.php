<?php

namespace App\Services\NajmHoda\Runtime;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class NajmHodaEntryPolicy
{
    public function check(string $entrypoint, ?int $userId, ?string $ipAddress, bool $enforceRateLimit = true): array
    {
        if (!config('najm-hoda.enabled', true)) {
            return [
                'allowed' => false,
                'status' => 503,
                'code' => 'NAJM_HODA_DISABLED',
                'message' => 'Najm Hoda is disabled.',
            ];
        }

        if (!$enforceRateLimit) {
            return ['allowed' => true];
        }

        $globalLimit = (int) config('najm-hoda.runtime.entry_policy.rate_limit.max_requests_per_minute', 120);
        $chatLimit = (int) config('najm-hoda.runtime.entry_policy.rate_limit.max_chat_requests_per_minute', 30);
        $opsMultiplier = (float) Cache::get('najm_hoda:ops:entry_rate_multiplier', 1.0);
        $opsMultiplier = max(0.1, min(1.0, $opsMultiplier));
        $globalLimit = max(1, (int) floor($globalLimit * $opsMultiplier));
        $chatLimit = max(1, (int) floor($chatLimit * $opsMultiplier));
        $entryLimit = str_contains($entrypoint, 'chat') ? $chatLimit : $globalLimit;
        $entryLimit = max(1, $entryLimit);

        $scope = $this->resolveScope($userId, $ipAddress);
        $bucket = now()->format('YmdHi');
        $globalKey = "najm_hoda:entry_policy:{$scope}:all:{$bucket}";
        $entryKey = "najm_hoda:entry_policy:{$scope}:{$entrypoint}:{$bucket}";

        $globalCount = (int) Cache::get($globalKey, 0);
        $entryCount = (int) Cache::get($entryKey, 0);

        if ($globalCount >= $globalLimit || $entryCount >= $entryLimit) {
            Log::info('NajmHoda entry policy denied request by rate limit', [
                'entrypoint' => $entrypoint,
                'scope' => $scope,
                'global_limit' => $globalLimit,
                'entry_limit' => $entryLimit,
            ]);

            return [
                'allowed' => false,
                'status' => 429,
                'code' => 'NAJM_HODA_RATE_LIMITED',
                'message' => 'Najm Hoda request rate limit exceeded.',
            ];
        }

        Cache::put($globalKey, $globalCount + 1, now()->addSeconds(70));
        Cache::put($entryKey, $entryCount + 1, now()->addSeconds(70));

        return ['allowed' => true];
    }

    protected function resolveScope(?int $userId, ?string $ipAddress): string
    {
        if ($userId !== null && $userId > 0) {
            return "user:{$userId}";
        }

        $ipAddress = trim((string) $ipAddress);
        if ($ipAddress !== '') {
            return "ip:{$ipAddress}";
        }

        return 'anonymous';
    }
}
