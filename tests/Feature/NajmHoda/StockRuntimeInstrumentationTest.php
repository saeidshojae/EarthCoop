<?php

namespace Tests\Feature\NajmHoda;

use App\Events\StockPriceChanged;
use App\Listeners\CaptureNajmHodaStockRuntimeInput;
use App\Modules\Stock\Models\Stock;
use App\Services\NajmHoda\Runtime\InMemoryRuntimeEventBus;
use App\Services\NajmHoda\Runtime\RuntimeEventBus;
use Tests\TestCase;

class StockRuntimeInstrumentationTest extends TestCase
{
    public function test_stock_price_change_is_visible_to_founder_operations(): void
    {
        $bus = new InMemoryRuntimeEventBus(100);
        $this->app->instance(RuntimeEventBus::class, $bus);

        $stock = new Stock();
        $stock->id = 12;

        $event = new StockPriceChanged(
            stock: $stock,
            oldPrice: 0.01,
            newPrice: 0.012,
            oldValuation: 1000000,
            newValuation: 1200000,
        );

        (new CaptureNajmHodaStockRuntimeInput($bus))->handle($event);

        $events = $bus->recent('najm_hoda.input.stock.stock_price_changed', 1);
        $this->assertNotEmpty($events);
        $payload = data_get($events[0], 'payload', []);

        $this->assertSame(12, (int) ($payload['stock_id'] ?? 0));
        $this->assertSame('stock', $payload['scope'] ?? null);
        $this->assertSame('high', $payload['risk'] ?? null);
        $this->assertSame(0.012, (float) ($payload['new_price'] ?? 0));
    }
}
