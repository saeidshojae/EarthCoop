<?php

namespace App\Modules\Stock\ExternalCapital\Dto;

use InvalidArgumentException;

final class VerifiedPaymentEvent
{
    public function __construct(
        public readonly string $eventId,
        public readonly string $eventType,
        public readonly string $resultStatus,
        public readonly int $amountMinor,
        public readonly string $currency,
        public readonly ?string $providerPaymentId = null,
        public readonly array $payload = [],
        public readonly array $metadata = [],
        public readonly ?\DateTimeInterface $occurredAt = null,
    ) {
        if (trim($eventId) === '' || trim($eventType) === '') {
            throw new InvalidArgumentException('Verified provider event id and type are required.');
        }

        if (! in_array($resultStatus, ['pending', 'confirmed', 'failed', 'cancelled', 'refunded', 'reversed'], true)) {
            throw new InvalidArgumentException('Unknown verified provider event result status.');
        }

        if ($amountMinor <= 0) {
            throw new InvalidArgumentException('Verified provider event amount must be positive.');
        }

        if (! in_array(strtoupper(trim($currency)), ['IRR', 'USD'], true)) {
            throw new InvalidArgumentException('Verified provider event currency must be IRR or USD.');
        }
    }
}
