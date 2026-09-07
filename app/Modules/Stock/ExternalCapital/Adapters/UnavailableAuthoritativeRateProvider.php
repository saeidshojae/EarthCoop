<?php

namespace App\Modules\Stock\ExternalCapital\Adapters;

use App\Modules\Stock\ExternalCapital\Contracts\AuthoritativeRateProvider;
use App\Modules\Stock\Pricing\FiatQuoteSnapshot;
use RuntimeException;

final class UnavailableAuthoritativeRateProvider implements AuthoritativeRateProvider
{
    public function sourceIdentifier(): string
    {
        return 'unavailable';
    }

    public function quote(int $golAmount, string $currency): FiatQuoteSnapshot
    {
        throw new RuntimeException('Authoritative rate provider is unavailable and external capital must remain fail-closed.');
    }
}
