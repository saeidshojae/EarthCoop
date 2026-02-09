<?php

namespace App\Events;

use App\Modules\Stock\Models\Wallet;
use App\Modules\Stock\Models\Bid;
use App\Models\User;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class WalletReleased
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public Wallet $wallet,
        public User $user,
        public float $amount,
        public ?Bid $bid = null,
        public ?string $description = null
    ) {
    }
}
