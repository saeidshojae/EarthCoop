<?php

namespace App\Modules\Stock\ExternalCapital\Contracts;

use App\Modules\Stock\Pricing\FiatQuoteSnapshot;

interface AuthoritativeRateProvider
{
    public function sourceIdentifier(): string;

    public function quote(int $golAmount, string $currency): FiatQuoteSnapshot;
}
