<?php

namespace App\Listeners;

use App\Services\NajmHoda\Runtime\RuntimeEventBus;
use Illuminate\Support\Str;

class CaptureNajmHodaStockRuntimeInput
{
    public function __construct(
        private RuntimeEventBus $runtimeEventBus
    ) {
    }

    public function handle(object $event): void
    {
        if (! config('najm-hoda.enabled', true)) {
            return;
        }

        $eventName = Str::snake(class_basename($event));
        $payload = [
            'source_event' => $event::class,
            'scope' => 'stock',
            'risk' => 'high',
            'operation' => $eventName,
        ];

        foreach ([
            'user' => 'user_id',
            'stock' => 'stock_id',
            'auction' => 'auction_id',
            'bid' => 'bid_id',
            'wallet' => 'wallet_id',
            'holding' => 'holding_id',
        ] as $property => $key) {
            if (property_exists($event, $property) && is_object($event->{$property})) {
                $payload[$key] = isset($event->{$property}->id)
                    ? (int) $event->{$property}->id
                    : null;
            }
        }

        foreach (['amount', 'quantity', 'oldPrice', 'newPrice', 'oldValuation', 'newValuation'] as $property) {
            if (! property_exists($event, $property)) {
                continue;
            }

            $key = Str::snake($property);
            $value = $event->{$property};
            $payload[$key] = is_numeric($value) ? (float) $value : $value;
        }

        $this->runtimeEventBus->emit('najm_hoda.input.stock.' . $eventName, $payload);
    }
}
