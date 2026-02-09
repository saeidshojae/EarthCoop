<?php

namespace App\Events;

use App\Modules\Stock\Models\Auction;
use App\Models\User;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class AuctionReminder
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public Auction $auction,
        public User $user,
        public string $timeRemaining
    ) {
    }
}
