<?php

namespace App\Contracts\Geocoding;

use App\Data\Geocoding\ReverseGeocodeResult;

interface ReverseGeocoder
{
    public function reverse(float $latitude, float $longitude, string $locale): ReverseGeocodeResult;
}
