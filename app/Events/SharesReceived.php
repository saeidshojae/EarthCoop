<?php

namespace App\Events;

use App\Modules\Stock\Models\Holding;
use App\Modules\Stock\Models\Bid;
use App\Modules\Stock\Models\Auction;
use App\Models\User;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class SharesReceived
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public Holding $holding,
        public User $user,
        public int $quantity,
        public ?Auction $auction = null,
        public ?Bid $bid = null,
        public ?string $description = null
    ) {
    }
}
