<?php

namespace Tests\Feature\Stock;

use App\Models\User;
use App\Modules\Stock\Models\Auction;
use App\Modules\Stock\Models\Bid;
use App\Modules\Stock\Models\ExternalPaymentIntent;
use App\Modules\Stock\Models\Stock;
use App\Modules\Stock\Models\Wallet;
use App\Modules\Stock\Pricing\FiatQuoteSnapshot;
use App\Modules\Stock\Services\ExternalCapitalPaymentService;
use App\Modules\Stock\Services\StockBidAcceptanceService;
use App\Modules\Stock\Settlement\SettlementChannel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExternalCapitalBidFundingLifecycleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('stock.external_capital.authoritative_quote_sources', ['test-rate']);
        config()->set('stock.external_capital.quote_max_age_seconds', 600);
        config()->set('stock.external_capital.quote_future_tolerance_seconds', 30);
    }

    public function test_external_bid_stays_awaiting_funding_until_its_payment_is_confirmed(): void
    {
        $user = User::factory()->create();
        $auction = $this->auction();
        $service = app(StockBidAcceptanceService::class);
        $quote = FiatQuoteSnapshot::fromRate(240, 'IRR', 500, 1, 'test-rate');

        $bid = $service->initiateExternalCapitalBid(
            $user->id,
            $auction,
            120,
            2,
            'bid:external:1',
            $quote,
            'manual'
        );

        $this->assertSame('awaiting_funding', $bid->status);
        $this->assertSame(120, (int) $bid->price_gol);
        $this->assertSame(2, (int) $bid->quantity);
        $this->assertNotNull($bid->external_payment_intent_id);
        $this->assertSame(0, Wallet::query()->count());

        $intent = ExternalPaymentIntent::query()->findOrFail($bid->external_payment_intent_id);
        $this->assertSame('stock_bid', $intent->reference_type);
        $this->assertSame((string) $bid->id, $intent->reference_id);
        $this->assertSame(240, (int) data_get($intent->quote_snapshot, 'gol_amount'));

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('confirmed');
        $service->activateExternallyFundedBid($bid->fresh());
    }

    public function test_confirmed_external_payment_activates_bid_without_stock_wallet_balance(): void
    {
        $user = User::factory()->create();
        $auction = $this->auction();
        $bidService = app(StockBidAcceptanceService::class);
        $paymentService = app(ExternalCapitalPaymentService::class);
        $quote = FiatQuoteSnapshot::fromRate(240, 'IRR', 500, 1, 'test-rate');

        $bid = $bidService->initiateExternalCapitalBid(
            $user->id,
            $auction,
            120,
            2,
            'bid:external:2',
            $quote,
            'manual'
        );

        $intent = $bid->fresh()->externalPaymentIntent;
        $paymentService->markPending($intent->intent_key, 'provider-intent-2', 'manual');
        $paymentService->reconcile(
            $intent->intent_key,
            'event:bid:external:2',
            'payment_confirmed',
            'confirmed',
            (int) $intent->amount_minor,
            'IRR',
            'provider-event-2',
            'provider-payment-2',
            'manual'
        );

        $active = $bidService->activateExternallyFundedBid($bid->fresh());

        $this->assertSame('active', $active->status);
        $this->assertSame(0, Wallet::query()->count());
    }

    public function test_external_bid_rejects_quote_for_a_different_gol_total(): void
    {
        $user = User::factory()->create();
        $auction = $this->auction();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Gol total');

        app(StockBidAcceptanceService::class)->initiateExternalCapitalBid(
            $user->id,
            $auction,
            120,
            2,
            'bid:external:mismatch',
            FiatQuoteSnapshot::fromRate(239, 'IRR', 500, 1, 'test-rate'),
            'manual'
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
            'settlement_channel' => SettlementChannel::EXTERNAL_IRR,
            'quote_unit' => 'gol',
            'shares_count' => 1000,
            'base_price' => 1,
            'base_price_gol' => 100,
            'start_time' => now()->subMinute(),
            'ends_at' => now()->addDay(),
            'status' => 'running',
            'type' => 'uniform_price',
            'lot_size' => 100,
        ]);
    }
}
