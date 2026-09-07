<?php

namespace Tests\Feature\Stock;

use App\Models\User;
use App\Modules\Stock\Models\Auction;
use App\Modules\Stock\Models\Bid;
use App\Modules\Stock\Models\ExternalPaymentIntent;
use App\Modules\Stock\Models\Stock;
use App\Modules\Stock\Models\StockSettlementAllocation;
use App\Modules\Stock\Pricing\FiatQuoteSnapshot;
use App\Modules\Stock\Services\ExternalCapitalPaymentService;
use App\Modules\Stock\Settlement\SettlementChannel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExternalCapitalRefundReversalTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('stock.external_capital.authoritative_quote_sources', ['official-fx']);
        config()->set('stock.external_capital.quote_max_age_seconds', 600);
        config()->set('stock.external_capital.quote_future_tolerance_seconds', 30);
    }

    public function test_confirmed_payment_can_be_fully_refunded_before_asset_settlement(): void
    {
        [$service, $auction, $intent] = $this->confirmedIntent('refund:1');
        $allocation = $this->allocation($auction, $intent, StockSettlementAllocation::PREPARED);

        $event = $service->reconcile(
            $intent->intent_key,
            'event:refund:1',
            'payment_refunded',
            'refunded',
            (int) $intent->amount_minor,
            'USD',
            'provider-event-refund-1',
            'provider-payment-refund-1',
            'provider-a'
        );

        $this->assertSame('refunded', $event->result_status);
        $this->assertSame(ExternalPaymentIntent::REFUNDED, $intent->fresh()->status);
        $this->assertSame(StockSettlementAllocation::CANCELLED, $allocation->fresh()->state);
        $this->assertSame('refunded_external', $allocation->fresh()->money_state);
        $this->assertDatabaseCount('stock_external_payment_reconciliations', 2);
    }

    public function test_confirmed_payment_can_be_reversed_before_asset_settlement(): void
    {
        [$service, $auction, $intent] = $this->confirmedIntent('reverse:1');
        $allocation = $this->allocation($auction, $intent, StockSettlementAllocation::RECONCILIATION_REQUIRED);

        $event = $service->reconcile(
            $intent->intent_key,
            'event:reverse:1',
            'payment_reversed',
            'reversed',
            (int) $intent->amount_minor,
            'USD',
            'provider-event-reverse-1',
            'provider-payment-reverse-1',
            'provider-a'
        );

        $this->assertSame('reversed', $event->result_status);
        $this->assertSame(ExternalPaymentIntent::REVERSED, $intent->fresh()->status);
        $this->assertSame(StockSettlementAllocation::CANCELLED, $allocation->fresh()->state);
        $this->assertSame('reversed_external', $allocation->fresh()->money_state);
    }

    public function test_refund_is_rejected_after_asset_has_settled(): void
    {
        [$service, $auction, $intent] = $this->confirmedIntent('refund:settled');
        $this->allocation($auction, $intent, StockSettlementAllocation::SETTLED);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('asset settlement');
        $service->reconcile(
            $intent->intent_key,
            'event:refund:settled',
            'payment_refunded',
            'refunded',
            (int) $intent->amount_minor,
            'USD',
            null,
            null,
            'provider-a'
        );
    }

    public function test_partial_refund_is_rejected_until_partial_refund_domain_exists(): void
    {
        [$service, , $intent] = $this->confirmedIntent('refund:partial');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('full amount');
        $service->reconcile(
            $intent->intent_key,
            'event:refund:partial',
            'payment_refunded',
            'refunded',
            (int) $intent->amount_minor - 1,
            'USD',
            null,
            null,
            'provider-a'
        );
    }

    public function test_refund_event_replay_is_idempotent(): void
    {
        [$service, , $intent] = $this->confirmedIntent('refund:replay');

        $first = $service->reconcile(
            $intent->intent_key,
            'event:refund:replay',
            'payment_refunded',
            'refunded',
            (int) $intent->amount_minor,
            'USD',
            null,
            null,
            'provider-a'
        );
        $second = $service->reconcile(
            $intent->intent_key,
            'event:refund:replay',
            'payment_refunded',
            'refunded',
            (int) $intent->amount_minor,
            'USD',
            null,
            null,
            'provider-a'
        );

        $this->assertSame($first->id, $second->id);
        $this->assertDatabaseCount('stock_external_payment_reconciliations', 2);
    }

    private function confirmedIntent(string $suffix): array
    {
        $service = app(ExternalCapitalPaymentService::class);
        $auction = $this->auction();
        $quote = FiatQuoteSnapshot::fromRate(1000, 'USD', 25, 2, 'official-fx');
        $intent = $service->createIntentForAuction(
            $auction,
            $quote,
            'intent:' . $suffix,
            'auction_bid',
            900,
            'provider-a'
        );
        $service->markPending($intent->intent_key, 'provider-intent-' . $suffix, 'provider-a');
        $service->reconcile(
            $intent->intent_key,
            'event:confirmed:' . $suffix,
            'payment_confirmed',
            'confirmed',
            (int) $intent->amount_minor,
            'USD',
            'provider-event-confirmed-' . $suffix,
            'provider-payment-' . $suffix,
            'provider-a'
        );

        return [$service, $auction, $intent->fresh()];
    }

    private function allocation(Auction $auction, ExternalPaymentIntent $intent, string $state): StockSettlementAllocation
    {
        $user = User::factory()->create();
        $bid = Bid::create([
            'acceptance_key' => 'acceptance:' . $intent->intent_key,
            'auction_id' => $auction->id,
            'user_id' => $user->id,
            'price' => 10,
            'price_gol' => 10,
            'quantity' => 100,
            'status' => 'active',
        ]);

        return StockSettlementAllocation::create([
            'allocation_key' => 'allocation:' . $intent->intent_key,
            'auction_id' => $auction->id,
            'bid_id' => $bid->id,
            'user_id' => $user->id,
            'stock_id' => $auction->stock_id,
            'settlement_channel' => SettlementChannel::EXTERNAL_USD,
            'quantity' => 100,
            'price_gol' => 10,
            'total_gol' => 1000,
            'state' => $state,
            'money_state' => 'confirmed_external',
            'asset_state' => $state === StockSettlementAllocation::SETTLED ? 'settled' : 'pending',
            'external_payment_intent_id' => $intent->id,
        ]);
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
