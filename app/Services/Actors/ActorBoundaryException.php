<?php

declare(strict_types=1);

namespace App\Services\Actors;

use RuntimeException;

final class ActorBoundaryException extends RuntimeException
{
    public function __construct(
        private readonly string $errorCode,
        private readonly int $httpStatus,
        private readonly ?array $details = null,
    ) {
        parent::__construct($errorCode);
    }

    public static function invalidReference(?array $details = null): self
    {
        return new self('actor_reference_invalid', 422, $details);
    }

    public static function notSupported(?array $details = null): self
    {
        return new self('actor_not_supported', 422, $details);
    }

    public static function notFound(?array $details = null): self
    {
        return new self('actor_not_found', 404, $details);
    }

    public static function representationForbidden(?array $details = null): self
    {
        return new self('actor_representation_forbidden', 403, $details);
    }

    public static function operationNotSupported(?array $details = null): self
    {
        return new self('actor_operation_not_supported', 422, $details);
    }

    public function errorCode(): string
    {
        return $this->errorCode;
    }

    public function httpStatus(): int
    {
        return $this->httpStatus;
    }

    public function details(): ?array
    {
        return $this->details;
    }
}
