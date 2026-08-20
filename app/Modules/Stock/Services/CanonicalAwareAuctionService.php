<?php

namespace App\Modules\Stock\Services;

use App\Modules\Stock\Models\Auction;
use App\Modules\Stock\Models\Bid;
use RuntimeException;

class CanonicalAwareAuctionService extends AuctionService
{
    public function validateAndPlaceBid(int $userId, Auction $auction, float $price, int $quantity): Bid
    {
        $this->assertLegacyOnly($auction, 'Legacy bid placement');
        return parent::validateAndPlaceBid($userId, $auction, $price, $quantity);
    }

    public function closeAuction(Auction $auction): array
    {
        $this->assertLegacyOnly($auction, 'Legacy auction settlement');
        return parent::closeAuction($auction);
    }

    public function manualSettleAuction(Auction $auction): array
    {
        $this->assertLegacyOnly($auction, 'Legacy manual settlement');
        return parent::manualSettleAuction($auction);
    }

    private function assertLegacyOnly(Auction $auction, string $operation): void
    {
        if ($auction->hasCanonicalGolPricing()) {
            throw new RuntimeException("{$operation} is disabled for canonical Gol auctions. Use canonical Stock services.");
        }
    }
}
