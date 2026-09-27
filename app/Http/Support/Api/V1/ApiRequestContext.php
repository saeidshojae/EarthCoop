<?php

namespace App\Http\Support\Api\V1;

final class ApiRequestContext
{
    public function __construct(
        private readonly string $requestId,
        private readonly string $locale,
        private readonly ?string $timezone,
        private readonly ?string $deviceId,
    ) {
    }

    public function requestId(): string
    {
        return $this->requestId;
    }

    public function locale(): string
    {
        return $this->locale;
    }

    public function timezone(): ?string
    {
        return $this->timezone;
    }

    public function deviceId(): ?string
    {
        return $this->deviceId;
    }
}
