<?php

namespace App\Data\Geocoding;

final readonly class ReverseGeocodeResult
{
    public function __construct(
        public ?string $countryCode,
        public array $components,
        public ?float $confidence = null,
        public ?string $provider = null,
        public array $metadata = [],
    ) {
    }
}
