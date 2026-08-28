<?php

namespace App\Modules\Stock\Services;

use App\Modules\NajmBahar\Models\ActiveBaharReservation;
use App\Modules\NajmBahar\Services\ActiveBaharReservationService;
use App\Modules\Stock\Models\Auction;
use App\Modules\Stock\Models\Bid;
use App\Modules\Stock\Models\StockSettlementAllocation;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class StockCanonicalAuctionSettlementService
{
    public function __construct(
        protected StockAtomicSettlementService $atomic,
        protected ActiveBaharReservationService $reservations
    ) {}

    /**
     * Finalize a canonical Gol auction from allocations prepared by the canonical
     * settlement pipeline. The preparation step is deliberately not inferred here:
     * reservation/payee/external-payment evidence must already exist on allocations.
     *
     * @return array<string,mixed>
     */
    public function settlePrepared(Auction $auction): array
    {
        $auction->loadMissing('stock');

        if (! $auction->hasCanonicalGolPricing()) {
            throw new RuntimeException('Auction is not configured for canonical Gol settlement.');
        }
        if ((string) $auction->status === 'settled') {
            return [
                'auction_id' => (int) $auction->id,
                'status' => 'settled',
                'allocation_count' => 0,
                'already_settled' => true,
            ];
        }
        if ((string) $auction->status !== 'settling' && ! $auction->isExpired()) {
            throw new RuntimeException('Canonical auction is not ready for settlement.');
        }

        $allocations = StockSettlementAllocation::query()
            ->where('auction_id', $auction->id)
            ->where('state', '!=', StockSettlementAllocation::CANCELLED)
            ->orderBy('id')
            ->get();

        if ($allocations->isEmpty()) {
            throw new RuntimeException('Canonical auction has no prepared settlement allocations.');
        }
        if ($allocations->contains(fn (StockSettlementAllocation $allocation): bool =>
            (string) $allocation->state === StockSettlementAllocation::RECONCILIATION_REQUIRED
        )) {
            throw new RuntimeException('Canonical auction has an allocation requiring reconciliation.');
        }

        $this->assertAllocationEnvelope($auction, $allocations->all());

        $settled = [];
        foreach ($allocations as $allocation) {
            $row = (string) $allocation->state === StockSettlementAllocation::SETTLED
                ? $allocation
                : $this->atomic->settle($allocation);
            $settled[] = (int) $row->id;
        }

        $remaining = StockSettlementAllocation::query()
            ->where('auction_id', $auction->id)
            ->where('state', '!=', StockSettlementAllocation::CANCELLED)
            ->where('state', '!=', StockSettlementAllocation::SETTLED)
            ->exists();
        if ($remaining) {
            throw new RuntimeException('Canonical auction settlement is incomplete and must be retried or reconciled.');
        }

        $winnerBidIds = StockSettlementAllocation::query()
            ->where('auction_id', $auction->id)
            ->where('state', StockSettlementAllocation::SETTLED)
            ->whereNotNull('bid_id')
            ->pluck('bid_id')
            ->map(fn ($id): int => (int) $id)
            ->unique()
            ->values()
            ->all();

        DB::transaction(function () use ($auction, $winnerBidIds): void {
            $lockedAuction = Auction::query()->whereKey($auction->id)->lockForUpdate()->firstOrFail();
            if ((string) $lockedAuction->status === 'settled') {
                return;
            }

            $losers = Bid::query()
                ->where('auction_id', $lockedAuction->id)
                ->where('status', 'active')
                ->when($winnerBidIds !== [], fn ($query) => $query->whereNotIn('id', $winnerBidIds))
                ->lockForUpdate()
                ->get();

            foreach ($losers as $bid) {
                if ($bid->reservation_key) {
                    $reservation = ActiveBaharReservation::query()
                        ->where('reservation_key', (string) $bid->reservation_key)
                        ->lockForUpdate()
                        ->first();

                    if ($reservation && $reservation->status === ActiveBaharReservation::RESERVED) {
                        $this->reservations->release(
                            (string) $bid->reservation_key,
                            'stock-auction-' . (int) $lockedAuction->id . ':loser-bid-' . (int) $bid->id . ':release'
                        );
                    } elseif ($reservation && $reservation->status === ActiveBaharReservation::SETTLED) {
                        throw new RuntimeException('A losing bid has a settled Active Bahar reservation and requires reconciliation.');
                    }
                }

                $bid->status = 'lost';
                $bid->save();
            }

            if ($winnerBidIds !== []) {
                Bid::query()->whereIn('id', $winnerBidIds)->update(['status' => 'won']);
            }

            $lockedAuction->status = 'settled';
            $lockedAuction->save();
        });

        $auction->refresh();

        return [
            'auction_id' => (int) $auction->id,
            'status' => (string) $auction->status,
            'allocation_count' => count($settled),
            'settled_allocation_ids' => $settled,
            'winner_bid_ids' => $winnerBidIds,
        ];
    }

    /** @param array<int,StockSettlementAllocation> $allocations */
    protected function assertAllocationEnvelope(Auction $auction, array $allocations): void
    {
        $total = 0;
        $perBid = [];

        foreach ($allocations as $allocation) {
            if ((int) $allocation->auction_id !== (int) $auction->id
                || (int) $allocation->stock_id !== (int) $auction->stock_id) {
                throw new RuntimeException('Canonical allocation does not belong to the auction stock envelope.');
            }

            $quantity = (int) $allocation->quantity;
            if ($quantity <= 0) {
                throw new RuntimeException('Canonical allocation quantity must be positive.');
            }
            $total += $quantity;

            if ($allocation->bid_id) {
                $bidId = (int) $allocation->bid_id;
                $perBid[$bidId] = ($perBid[$bidId] ?? 0) + $quantity;
            }
        }

        if ($total > (int) $auction->shares_count) {
            throw new RuntimeException('Canonical allocations exceed auction share supply.');
        }

        foreach ($perBid as $bidId => $quantity) {
            $bid = Bid::query()->find($bidId);
            if (! $bid || (int) $bid->auction_id !== (int) $auction->id) {
                throw new RuntimeException('Canonical allocation references an invalid auction bid.');
            }
            if ($quantity > (int) $bid->quantity) {
                throw new RuntimeException('Canonical allocations exceed a bid quantity.');
            }
        }
    }
}
