<?php

namespace App\Modules\Stock\Services;

use App\Modules\Stock\Models\Bid;
use App\Modules\Stock\Models\ExternalPaymentIntent;

final class ExternalCapitalBidCheckout
{
    public function __construct(
        public readonly Bid $bid,
        public readonly ExternalPaymentIntent $paymentIntent,
        public readonly string $redirectUrl,
    ) {}
}
