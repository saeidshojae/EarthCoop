<?php

namespace App\Services\Geocoding;

use App\Contracts\Geocoding\ReverseGeocoder;
use App\Data\Geocoding\ReverseGeocodeResult;
use RuntimeException;

class UnavailableReverseGeocoder implements ReverseGeocoder
{
    public function reverse(float $latitude, float $longitude, string $locale): ReverseGeocodeResult
    {
        throw new RuntimeException('No reverse geocoding provider is configured.');
    }
}
