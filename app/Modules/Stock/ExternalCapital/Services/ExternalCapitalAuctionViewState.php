<?php

namespace App\Modules\Stock\ExternalCapital\Services;

use App\Modules\Stock\Models\Auction;
use App\Modules\Stock\Settlement\SettlementChannel;
use InvalidArgumentException;
use RuntimeException;

final class ExternalCapitalAuctionViewState
{
    public function __construct(
        private readonly ExternalCapitalReadinessGate $readiness,
    ) {}

    /**
     * @return array{externalCurrency: ?string, externalCheckoutReady: bool}
     */
    public function forAuction(Auction $auction): array
    {
        $currency = match ((string) $auction->settlement_channel) {
            SettlementChannel::EXTERNAL_IRR => 'IRR',
            SettlementChannel::EXTERNAL_USD => 'USD',
            default => null,
        };

        $stock = $auction->relationLoaded('stock')
            ? $auction->stock
            : $auction->stock()->first();

        $isEarthCoopPrimaryTreasury =
            strtolower((string) $auction->market_type) === 'primary'
            && strtolower((string) $auction->supply_source) === 'treasury'
            && strtolower((string) ($stock?->issuer_type ?? '')) === 'earthcoop';

        $externalCheckoutReady = false;

        if ($isEarthCoopPrimaryTreasury && $currency !== null) {
            try {
                $this->readiness->assertReadyForCurrency($currency);
                $externalCheckoutReady = true;
            } catch (RuntimeException | InvalidArgumentException) {
                $externalCheckoutReady = false;
            }
        }

        return [
            'externalCurrency' => $currency,
            'externalCheckoutReady' => $externalCheckoutReady,
        ];
    }
}
