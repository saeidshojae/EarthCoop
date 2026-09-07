<?php

namespace App\Listeners;

use App\Events\BidCancelled;
use App\Services\ReputationService;
use Illuminate\Support\Facades\Log;
use Throwable;

class AwardBidCancellationReputation
{
    public function __construct(protected ReputationService $reputationService)
    {
    }

    public function handle(BidCancelled $event): void
    {
        try {
            $this->reputationService->applyAction(
                $event->user,
                'bid_canceled',
                [
                    'bid_id' => $event->bid->id,
                    'auction_id' => $event->auction->id,
                ],
                $event->bid->id,
                'stock.bid',
                'bid_canceled:bid:' . $event->bid->id . ':user:' . $event->user->id
            );
        } catch (Throwable $e) {
            // Cancelling a bid must not be rolled back solely because reputation recording failed.
            Log::warning('Bid cancellation reputation penalty failed', [
                'bid_id' => $event->bid->id,
                'user_id' => $event->user->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
