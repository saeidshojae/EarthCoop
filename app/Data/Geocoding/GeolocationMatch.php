<?php

namespace App\Data\Geocoding;

final readonly class GeolocationMatch
{
    public function __construct(
        public string $status,
        public ?int $suggestedLocationId,
        public ?int $manualLocationId,
        public ?float $confidence,
        public bool $gpsConsistent,
        public bool $manualSelectionAvailable = true,
        public ?string $provider = null,
    ) {
    }
}
