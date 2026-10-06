<?php

namespace App\Services\Push;

final readonly class PushDeliveryResult
{
    public const DELIVERED = 'delivered';
    public const TEMPORARY_FAILURE = 'temporary_failure';
    public const INVALID_TOKEN = 'invalid_token';
    public const PERMANENT_FAILURE = 'permanent_failure';

    public function __construct(
        public string $status,
        public ?string $code = null,
    ) {
    }

    public static function success(): self
    {
        return new self(self::DELIVERED);
    }

    public static function temporaryFailure(?string $code = null): self
    {
        return new self(self::TEMPORARY_FAILURE, $code);
    }

    public static function invalidToken(?string $code = null): self
    {
        return new self(self::INVALID_TOKEN, $code);
    }

    public static function permanentFailure(?string $code = null): self
    {
        return new self(self::PERMANENT_FAILURE, $code);
    }

    public function delivered(): bool
    {
        return $this->status === self::DELIVERED;
    }
}
