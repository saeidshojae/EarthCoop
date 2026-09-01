<?php

namespace App\Modules\Stock\ExternalCapital\Dto;

use App\Modules\Stock\Models\Bid;
use App\Modules\Stock\Models\ExternalPaymentIntent;
use InvalidArgumentException;

final class ExternalCapitalBidCheckout
{
    public function __construct(
        public readonly Bid $bid,
        public readonly ExternalPaymentIntent $paymentIntent,
        public readonly string $redirectUrl,
    ) {
        if (trim($redirectUrl) === '' || filter_var($redirectUrl, FILTER_VALIDATE_URL) === false) {
            throw new InvalidArgumentException('External payment redirect URL is invalid.');
        }

        if ((int) $bid->external_payment_intent_id !== (int) $paymentIntent->id) {
            throw new InvalidArgumentException('External bid checkout payment intent does not belong to the bid.');
        }
    }
}
