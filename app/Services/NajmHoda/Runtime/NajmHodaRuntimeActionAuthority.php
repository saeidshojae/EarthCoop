<?php

namespace App\Services\NajmHoda\Runtime;

/**
 * Server-side capability token for chat-originated runtime actions.
 *
 * This object cannot be produced by browser JSON. Callers that want to request
 * a runtime action must construct it inside trusted application code after
 * authorization. User-provided booleans/strings are never sufficient.
 */
final class NajmHodaRuntimeActionAuthority
{
    public function __construct(
        public readonly ?int $actorId,
        public readonly bool $allowApply = false,
        public readonly string $source = 'internal'
    ) {
    }

    public static function propose(?int $actorId, string $source = 'internal'): self
    {
        return new self($actorId, false, $source);
    }

    public static function apply(?int $actorId, string $source = 'internal'): self
    {
        return new self($actorId, true, $source);
    }
}
