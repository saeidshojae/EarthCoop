<?php

namespace App\Events;

use App\Modules\Stock\Models\Stock;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class StockPriceChanged
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public Stock $stock,
        public ?float $oldPrice = null,
        public ?float $newPrice = null,
        public ?float $oldValuation = null,
        public ?float $newValuation = null
    ) {
    }
}
