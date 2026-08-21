<?php

namespace App\Services\NajmHoda\FounderOps;

use App\Models\User;
use App\Modules\Stock\Models\Auction;
use App\Modules\Stock\Services\AuctionService;
use App\Modules\Stock\Services\StockCanonicalAuctionSettlementService;
use App\Modules\Stock\Models\StockSettlementAllocation;
use App\Services\NajmHoda\Runtime\NajmHodaAutonomyApprovalService;

class FounderStockDecisionService
{
    public function __construct(
        protected FounderActionRequestService $requests,
        protected FounderActionExecutionService $execution,
        protected NajmHodaAutonomyApprovalService $approvals,
        protected AuctionService $auctions,
        protected StockCanonicalAuctionSettlementService $canonicalSettlements
    ) {}

    /** @return array<string,mixed> */
    public function requestSettle(Auction $auction, int $requestedBy, ?string $reasonCode = null): array
    {
        if ((string) $auction->status === 'settled') {
            return ['success' => false, 'status' => 'skipped', 'reason' => 'auction_already_settled'];
        }

        if ((string) $auction->status !== 'settling' && ! $auction->isExpired()) {
            return ['success' => false, 'status' => 'blocked', 'reason' => 'auction_not_ready_for_settlement'];
        }

        if ($auction->hasCanonicalGolPricing()) {
            $hasPreparedEnvelope = StockSettlementAllocation::query()
                ->where('auction_id', $auction->id)
                ->where('state', '!=', StockSettlementAllocation::CANCELLED)
                ->exists();
            if (! $hasPreparedEnvelope) {
                return ['success' => false, 'status' => 'blocked', 'reason' => 'canonical_settlement_allocations_not_prepared'];
            }
        }

        return $this->requests->prepare('stock', 'settle_auction', [
            'entity_type' => 'stock_auction',
            'entity_id' => (int) $auction->id,
            'requested_by' => $requestedBy,
            'reason_code' => $reasonCode ?: 'stock-settle-auction-' . (int) $auction->id,
            'source_event' => 'founder_ops_stock_auction',
        ]);
    }

    /** @return array<string,mixed> */
    public function decideAndExecute(string $requestId, string $decision, int $founderId, ?string $reason = null): array
    {
        if (! in_array($founderId, $this->founderIds(), true)) {
            return ['success' => false, 'status' => 'forbidden', 'reason' => 'founder_not_authorized'];
        }

        $pending = collect($this->approvals->pending(200))
            ->first(fn (array $item): bool => (string) ($item['id'] ?? '') === $requestId);
        if (! is_array($pending)) {
            return ['success' => false, 'status' => 'not_found', 'reason' => 'approval_request_not_pending'];
        }

        $domain = (string) data_get($pending, 'plan_item.domain');
        $action = (string) data_get($pending, 'plan_item.domain_action');
        $entityType = (string) data_get($pending, 'context.entity_type');
        $entityId = (int) data_get($pending, 'context.entity_id', 0);

        if ($domain !== 'stock' || $action !== 'settle_auction') {
            return ['success' => false, 'status' => 'invalid_request', 'reason' => 'approval_contract_mismatch'];
        }
        if ($entityType !== 'stock_auction' || $entityId < 1) {
            return ['success' => false, 'status' => 'invalid_request', 'reason' => 'approval_entity_mismatch'];
        }

        $founder = User::query()->find($founderId);
        if (! $founder) {
            return ['success' => false, 'status' => 'not_found', 'reason' => 'founder_user_not_found'];
        }

        $auction = Auction::query()->find($entityId);
        if (! $auction) {
            return ['success' => false, 'status' => 'not_found', 'reason' => 'stock_auction_not_found'];
        }
        if ((string) $auction->status === 'settled') {
            return ['success' => false, 'status' => 'skipped', 'reason' => 'auction_already_settled'];
        }
        if ((string) $auction->status !== 'settling' && ! $auction->isExpired()) {
            return ['success' => false, 'status' => 'blocked', 'reason' => 'auction_not_ready_for_settlement'];
        }
        if ($auction->hasCanonicalGolPricing()) {
            $hasPreparedEnvelope = StockSettlementAllocation::query()
                ->where('auction_id', $auction->id)
                ->where('state', '!=', StockSettlementAllocation::CANCELLED)
                ->exists();
            if (! $hasPreparedEnvelope) {
                return ['success' => false, 'status' => 'blocked', 'reason' => 'canonical_settlement_allocations_not_prepared'];
            }
        }

        $decisionResult = $this->approvals->decide($requestId, $decision, $founderId, $reason);
        if (! (bool) ($decisionResult['success'] ?? false)) {
            return $decisionResult;
        }
        if ($decision === 'reject') {
            return ['success' => true, 'status' => 'rejected', 'entity_type' => $entityType, 'entity_id' => $entityId];
        }

        return $this->execution->execute(
            'stock',
            'settle_auction',
            function () use ($auction): array {
                if ($auction->hasCanonicalGolPricing()) {
                    $result = $this->canonicalSettlements->settlePrepared($auction);
                } else {
                    $result = (string) $auction->status === 'settling'
                        ? $this->auctions->manualSettleAuction($auction)
                        : $this->auctions->closeAuction($auction);
                }

                $auction->refresh();

                return [
                    'auction_id' => (int) $auction->id,
                    'auction_status' => (string) $auction->status,
                    'settlement' => $result,
                ];
            },
            $requestId,
            ['entity_type' => 'stock_auction', 'entity_id' => $entityId, 'requested_by' => $founderId]
        );
    }

    /** @return array<int,int> */
    protected function founderIds(): array
    {
        return array_values(array_filter(array_map(
            'intval',
            (array) config('najm-hoda-founder-action-policy.founder_approval.user_ids', [])
        )));
    }
}
