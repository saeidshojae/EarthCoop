<?php

namespace Tests\Support\Geocoding;

use App\Contracts\Geocoding\ReverseGeocoder;
use App\Data\Geocoding\ReverseGeocodeResult;
use Throwable;

final class FakeReverseGeocoder implements ReverseGeocoder
{
    private function __construct(
        private readonly ?ReverseGeocodeResult $result = null,
        private readonly ?Throwable $exception = null,
    ) {
    }

    public static function returning(ReverseGeocodeResult $result): self
    {
        return new self(result: $result);
    }

    public static function throwing(Throwable $exception): self
    {
        return new self(exception: $exception);
    }

    public function reverse(float $latitude, float $longitude, string $locale): ReverseGeocodeResult
    {
        if ($this->exception !== null) {
            throw $this->exception;
        }

        return $this->result ?? new ReverseGeocodeResult(null, []);
    }
}
