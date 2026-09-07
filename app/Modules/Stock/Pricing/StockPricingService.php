<?php

namespace App\Modules\Stock\Pricing;

use App\Modules\Stock\Models\Auction;
use App\Modules\Stock\Models\Bid;
use App\Modules\Stock\Models\Stock;
use InvalidArgumentException;
use RuntimeException;

class StockPricingService
{
    public function setStockPrice(Stock $stock,int $sharePriceGol): Stock
    {
        $this->positive($sharePriceGol,'Share price');
        if ((int)$stock->total_shares > 0 && $sharePriceGol > intdiv(PHP_INT_MAX,(int)$stock->total_shares)) {
            throw new InvalidArgumentException('Stock valuation exceeds integer range.');
        }
        $stock->forceFill([
            'base_share_price_gol'=>$sharePriceGol,
            'startup_valuation_gol'=>$sharePriceGol*(int)$stock->total_shares,
        ])->save();
        return $stock->fresh();
    }

    public function configureAuction(Auction $auction,int $basePriceGol,?int $minBidGol=null,?int $maxBidGol=null): Auction
    {
        $this->positive($basePriceGol,'Auction base price');
        if ($minBidGol!==null) $this->positive($minBidGol,'Minimum bid');
        if ($maxBidGol!==null) $this->positive($maxBidGol,'Maximum bid');
        if ($minBidGol!==null && $maxBidGol!==null && $minBidGol>$maxBidGol) throw new InvalidArgumentException('Minimum bid cannot exceed maximum bid.');
        if ($minBidGol!==null && $basePriceGol<$minBidGol) throw new InvalidArgumentException('Base price cannot be below minimum bid.');
        if ($maxBidGol!==null && $basePriceGol>$maxBidGol) throw new InvalidArgumentException('Base price cannot exceed maximum bid.');
        $auction->forceFill(['base_price_gol'=>$basePriceGol,'min_bid_gol'=>$minBidGol,'max_bid_gol'=>$maxBidGol,'quote_unit'=>'gol'])->save();
        return $auction->fresh();
    }

    public function canonicalBidTotal(int $priceGol,int $quantity): int
    {
        $this->positive($priceGol,'Bid price'); $this->positive($quantity,'Bid quantity');
        if ($priceGol>intdiv(PHP_INT_MAX,$quantity)) throw new InvalidArgumentException('Bid total exceeds integer range.');
        return $priceGol*$quantity;
    }

    public function assertCanonicalAuction(Auction $auction): void
    {
        if (strtolower((string)$auction->quote_unit)!=='gol') throw new RuntimeException('Canonical auction quote_unit must be gol.');
        if ((int)($auction->base_price_gol??0)<=0) throw new RuntimeException('Canonical auction requires base_price_gol.');
    }

    public function assertCanonicalBid(Bid $bid): void
    {
        if ((int)($bid->price_gol??0)<=0) throw new RuntimeException('Canonical bid requires price_gol.');
        $this->canonicalBidTotal((int)$bid->price_gol,(int)$bid->quantity);
    }

    protected function positive(int $value,string $label): void
    {
        if ($value<=0) throw new InvalidArgumentException("{$label} must be a positive integer Gol value.");
    }
}
