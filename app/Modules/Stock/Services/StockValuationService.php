<?php

namespace App\Modules\Stock\Services;

use App\Modules\Stock\Models\Stock;
use App\Modules\Stock\Pricing\BaharGolConverter;
use InvalidArgumentException;
use OverflowException;

final class StockValuationService
{
    public function __construct(private readonly BaharGolConverter $converter)
    {
    }

    public function configure(
        Stock $stock,
        string $valuationBahar,
        int $totalShares,
        ?int $availableShares,
        ?string $info
    ): Stock {
        if ($totalShares <= 0) {
            throw new InvalidArgumentException('Total shares must be greater than zero.');
        }

        $availableShares ??= $totalShares;

        if ($availableShares < 0 || $availableShares > $totalShares) {
            throw new InvalidArgumentException('Available shares must be between zero and total shares.');
        }

        $valuationGol = $this->converter->toGol($valuationBahar);

        if ($valuationGol % $totalShares !== 0) {
            throw new InvalidArgumentException('Valuation must produce an exact integer Gol value per share.');
        }

        $baseSharePriceGol = intdiv($valuationGol, $totalShares);
        if ($baseSharePriceGol <= 0) {
            throw new InvalidArgumentException('Base share price must be at least one Gol.');
        }

        if ($baseSharePriceGol > intdiv(PHP_INT_MAX, $totalShares)) {
            throw new OverflowException('Canonical Stock market capitalization exceeds the supported integer range.');
        }

        // Legacy decimal columns are non-null in the original Stock schema. They are
        // compatibility mirrors only; canonical pricing always reads the *_gol fields.
        $stock->startup_valuation = $this->converter->toBaharString($valuationGol);
        $stock->startup_valuation_gol = $valuationGol;
        $stock->total_shares = $totalShares;
        $stock->base_share_price = $this->converter->toBaharString($baseSharePriceGol);
        $stock->base_share_price_gol = $baseSharePriceGol;
        $stock->available_shares = $availableShares;
        $stock->info = $info;
        $stock->save();

        return $stock->refresh();
    }
}
