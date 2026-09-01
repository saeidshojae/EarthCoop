<?php

namespace App\Modules\Stock\Pricing;

use Carbon\CarbonImmutable;
use RuntimeException;

final class ExternalCapitalQuotePolicy
{
    public function assertEligible(FiatQuoteSnapshot $quote): void
    {
        $sources = collect((array) config('stock.external_capital.authoritative_quote_sources', []))
            ->map(fn ($source) => trim((string) $source))
            ->filter()
            ->values()
            ->all();

        if ($sources === [] || ! in_array($quote->source, $sources, true)) {
            throw new RuntimeException('External capital quote source is not authoritative.');
        }

        $maxAgeSeconds = max(1, (int) config('stock.external_capital.quote_max_age_seconds', 300));
        $futureToleranceSeconds = max(0, (int) config('stock.external_capital.quote_future_tolerance_seconds', 30));

        try {
            $quotedAt = CarbonImmutable::parse($quote->quotedAt);
        } catch (\Throwable $e) {
            throw new RuntimeException('External capital quote timestamp is invalid.', previous: $e);
        }

        $now = CarbonImmutable::now();

        if ($quotedAt->greaterThan($now->addSeconds($futureToleranceSeconds))) {
            throw new RuntimeException('External capital quote timestamp is too far in the future.');
        }

        if ($quotedAt->lessThan($now->subSeconds($maxAgeSeconds))) {
            throw new RuntimeException('External capital quote has expired.');
        }
    }
}
