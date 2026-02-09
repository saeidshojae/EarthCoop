<?php

namespace App\Events;

use App\Modules\Stock\Models\Holding;
use App\Modules\Stock\Models\Stock;
use App\Models\User;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class SharesGifted
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public Holding $holding,
        public User $user,
        public Stock $stock,
        public int $quantity,
        public ?string $description = null
    ) {
    }
}
