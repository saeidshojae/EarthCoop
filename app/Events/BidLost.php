<?php

namespace App\Events;

use App\Modules\Stock\Models\Bid;
use App\Modules\Stock\Models\Auction;
use App\Models\User;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class BidLost
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public Bid $bid,
        public Auction $auction,
        public User $user
    ) {
    }
}
