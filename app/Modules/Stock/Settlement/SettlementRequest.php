<?php

namespace App\Modules\Stock\Settlement;

use InvalidArgumentException;

final class SettlementRequest
{
    public function __construct(
        public readonly string $channel,
        public readonly int $amount,
        public readonly string $idempotencyKey,
        public readonly string $referenceType,
        public readonly int|string $referenceId,
        public readonly ?string $payerAccountNumber = null,
        public readonly ?string $payeeAccountNumber = null,
        public readonly array $metadata = [],
    ) {
        if (! in_array($channel, SettlementChannel::all(), true)) {
            throw new InvalidArgumentException('Unknown settlement channel.');
        }

        if ($amount <= 0) {
            throw new InvalidArgumentException('Settlement amount must be a positive integer.');
        }

        if (trim($idempotencyKey) === '') {
            throw new InvalidArgumentException('Settlement idempotency key is required.');
        }

        if (trim($referenceType) === '') {
            throw new InvalidArgumentException('Settlement reference type is required.');
        }

        if ((string) $referenceId === '') {
            throw new InvalidArgumentException('Settlement reference id is required.');
        }
    }
}
