<?php

namespace App\Modules\Stock\Settlement;

use InvalidArgumentException;

final class SettlementReceipt
{
    public const RESERVED = 'reserved';
    public const RELEASED = 'released';
    public const SETTLED = 'settled';
    public const REFUNDED = 'refunded';

    public function __construct(
        public readonly string $channel,
        public readonly string $status,
        public readonly int $amount,
        public readonly string $idempotencyKey,
        public readonly ?string $providerReference = null,
        public readonly array $metadata = [],
    ) {
        if (! in_array($channel, SettlementChannel::all(), true)) {
            throw new InvalidArgumentException('Unknown settlement channel.');
        }

        if (! in_array($status, [self::RESERVED, self::RELEASED, self::SETTLED, self::REFUNDED], true)) {
            throw new InvalidArgumentException('Unknown settlement receipt status.');
        }

        if ($amount <= 0) {
            throw new InvalidArgumentException('Settlement receipt amount must be a positive integer.');
        }

        if (trim($idempotencyKey) === '') {
            throw new InvalidArgumentException('Settlement receipt idempotency key is required.');
        }
    }
}
