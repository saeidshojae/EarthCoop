<?php

namespace App\Modules\Stock\Services;

use App\Modules\Stock\Models\Auction;
use App\Modules\Stock\Models\StockSettlementAllocation;
use RuntimeException;

final class EarthCoopPrimaryOfferingPolicy
{
    /** @return array<string,int|string> */
    public function assertEligible(Auction $auction): array
    {
        $auction->loadMissing('stock');
        $stock = $auction->stock;

        if (! $stock
            || (string) $stock->issuer_type !== 'earthcoop'
            || (string) $auction->market_type !== 'primary'
            || (string) $auction->supply_source !== 'treasury') {
            throw new RuntimeException('Offering must be an EarthCoop primary treasury offering.');
        }

        $totalShares = (int) $stock->total_shares;
        $availableShares = (int) $stock->available_shares;
        $offeringShares = (int) $auction->shares_count;
        $maxBps = (int) config('stock.primary_offering.max_allocation_bps', 1000);

        if ($totalShares <= 0 || $availableShares < 0 || $availableShares > $totalShares || $offeringShares <= 0) {
            throw new RuntimeException('EarthCoop treasury share envelope is invalid.');
        }
        if ($maxBps <= 0 || $maxBps > 10000) {
            throw new RuntimeException('EarthCoop primary allocation cap configuration is invalid.');
        }
        if ($totalShares > intdiv(PHP_INT_MAX, $maxBps)) {
            throw new RuntimeException('EarthCoop primary allocation cap exceeds integer range.');
        }

        $maxPrimaryShares = intdiv($totalShares * $maxBps, 10000);
        if ($maxPrimaryShares <= 0 || $offeringShares > $maxPrimaryShares) {
            throw new RuntimeException('EarthCoop offering exceeds the configured primary allocation cap.');
        }
        if ($offeringShares > $availableShares) {
            throw new RuntimeException('EarthCoop offering exceeds real treasury available shares.');
        }

        $settledPrimaryShares = (int) StockSettlementAllocation::query()
            ->where('stock_id', $stock->id)
            ->where('state', StockSettlementAllocation::SETTLED)
            ->whereHas('auction', function ($query): void {
                $query->where('market_type', 'primary')
                    ->where('supply_source', 'treasury');
            })
            ->sum('quantity');

        $otherOpenShares = (int) Auction::query()
            ->where('stock_id', $stock->id)
            ->where('market_type', 'primary')
            ->where('supply_source', 'treasury')
            ->whereIn('status', ['scheduled', 'running', 'settling'])
            ->when($auction->exists, fn ($query) => $query->where('id', '!=', $auction->getKey()))
            ->sum('shares_count');

        if ($settledPrimaryShares > PHP_INT_MAX - $otherOpenShares
            || $settledPrimaryShares + $otherOpenShares > PHP_INT_MAX - $offeringShares) {
            throw new RuntimeException('EarthCoop primary offering envelope exceeds integer range.');
        }

        if ($settledPrimaryShares + $otherOpenShares + $offeringShares > $maxPrimaryShares) {
            throw new RuntimeException('EarthCoop open offerings would oversubscribe the primary allocation cap.');
        }

        $policyVersion = trim((string) config('stock.primary_offering.policy_version', ''));
        $disclosureVersion = trim((string) config('stock.primary_offering.disclosure_version', ''));
        if ($policyVersion === '' || $disclosureVersion === '') {
            throw new RuntimeException('EarthCoop primary offering policy/disclosure version is required.');
        }

        return [
            'policy_version' => $policyVersion,
            'disclosure_version' => $disclosureVersion,
            'max_allocation_bps' => $maxBps,
            'max_primary_shares' => $maxPrimaryShares,
            'settled_primary_shares' => $settledPrimaryShares,
            'other_open_offering_shares' => $otherOpenShares,
            'offering_shares' => $offeringShares,
            'treasury_available_shares' => $availableShares,
        ];
    }
}
