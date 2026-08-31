<?php

namespace App\Modules\Stock\ExternalCapital\Dto;

use InvalidArgumentException;

final class ProviderPaymentIntent
{
    public function __construct(
        public readonly string $providerIntentId,
        public readonly string $currency,
        public readonly int $amountMinor,
        public readonly array $metadata = [],
    ) {
        if (trim($providerIntentId) === '') {
            throw new InvalidArgumentException('Provider payment intent id is required.');
        }

        $currency = strtoupper(trim($currency));
        if (! in_array($currency, ['IRR', 'USD'], true)) {
            throw new InvalidArgumentException('Provider payment intent currency must be IRR or USD.');
        }

        if ($amountMinor <= 0) {
            throw new InvalidArgumentException('Provider payment intent amount must be positive.');
        }
    }
}
