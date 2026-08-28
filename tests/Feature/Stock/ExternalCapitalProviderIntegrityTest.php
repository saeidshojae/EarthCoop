<?php

namespace Tests\Feature\Stock;

use App\Modules\Stock\Models\Auction;
use App\Modules\Stock\Models\Stock;
use App\Modules\Stock\Pricing\FiatQuoteSnapshot;
use App\Modules\Stock\Services\ExternalCapitalPaymentService;
use App\Modules\Stock\Settlement\SettlementChannel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExternalCapitalProviderIntegrityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('stock.external_capital.authoritative_quote_sources', ['official-fx']);
        config()->set('stock.external_capital.quote_max_age_seconds', 600);
        config()->set('stock.external_capital.quote_future_tolerance_seconds', 30);
    }

    public function test_intent_idempotency_key_cannot_be_replayed_with_different_provider(): void
    {
        $service = app(ExternalCapitalPaymentService::class);
        $auction = $this->auction();
        $quote = FiatQuoteSnapshot::fromRate(1000, 'USD', 25, 2, 'official-fx');
        $service->createIntentForAuction($auction, $quote, 'intent:provider:0', 'auction_bid', 800, 'provider-a');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('provider');
        $service->createIntentForAuction($auction, $quote, 'intent:provider:0', 'auction_bid', 800, 'provider-b');
    }

    public function test_pending_transition_cannot_switch_payment_provider(): void
    {
        $service = app(ExternalCapitalPaymentService::class);
        $auction = $this->auction();
        $quote = FiatQuoteSnapshot::fromRate(1000, 'USD', 25, 2, 'official-fx');
        $service->createIntentForAuction($auction, $quote, 'intent:provider:1', 'auction_bid', 801, 'provider-a');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('provider');
        $service->markPending('intent:provider:1', 'provider-intent-1', 'provider-b');
    }

    public function test_reconciliation_cannot_switch_payment_provider(): void
    {
        $service = app(ExternalCapitalPaymentService::class);
        $auction = $this->auction();
        $quote = FiatQuoteSnapshot::fromRate(1000, 'USD', 25, 2, 'official-fx');
        $service->createIntentForAuction($auction, $quote, 'intent:provider:2', 'auction_bid', 802, 'provider-a');
        $service->markPending('intent:provider:2', 'provider-intent-2', 'provider-a');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('provider');
        $service->reconcile(
            'intent:provider:2',
            'event:provider:2',
            'payment_confirmed',
            'confirmed',
            12500,
            'USD',
            'provider-event-2',
            'provider-payment-2',
            'provider-b'
        );
    }

    private function auction(): Auction
    {
        $stock = Stock::create([
            'issuer_type' => 'earthcoop',
            'startup_valuation' => 1000000,
            'startup_valuation_gol' => 100000000,
            'total_shares' => 100000000,
            'available_shares' => 1000000,
            'base_share_price' => 0.01,
            'base_share_price_gol' => 1,
        ]);

        return Auction::create([
            'stock_id' => $stock->id,
            'market_type' => 'primary',
            'supply_source' => 'treasury',
            'settlement_channel' => SettlementChannel::EXTERNAL_USD,
            'quote_unit' => 'gol',
            'shares_count' => 100,
            'base_price' => 1,
            'base_price_gol' => 10,
            'start_time' => now(),
            'ends_at' => now()->addDay(),
            'status' => 'running',
            'type' => 'uniform_price',
            'lot_size' => 1,
        ]);
    }
}
