<?php

namespace App\Services\Auth\Data;

use App\Models\NativeDevice;
use Carbon\CarbonInterface;
use Laravel\Sanctum\PersonalAccessToken;

final class IssuedNativeSession
{
    public function __construct(
        public readonly NativeDevice $device,
        public readonly PersonalAccessToken $accessToken,
        public readonly string $plainTextToken,
        public readonly CarbonInterface $expiresAt,
    ) {
    }
}
