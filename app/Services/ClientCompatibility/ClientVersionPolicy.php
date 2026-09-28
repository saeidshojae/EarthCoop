<?php

namespace App\Services\ClientCompatibility;

final readonly class ClientVersionPolicy
{
    public function __construct(
        public string $platform,
        public string $minimumVersion,
        public string $latestVersion,
    ) {
    }
}
