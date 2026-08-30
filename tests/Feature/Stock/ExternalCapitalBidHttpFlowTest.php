<?php

namespace Tests\Feature\Stock;

use App\Models\User;
use App\Modules\Stock\ExternalCapital\Contracts\AuthoritativeRateProvider;
use App\Modules\Stock\ExternalCapital\Contracts\ExternalPaymentProvider;
use App\Modules\Stock\ExternalCapital\Dto\ProviderPaymentIntent;
use App\Modules\Stock\ExternalCapital\Dto\VerifiedPaymentEvent;
use App\Modules\Stock\Models\Auction;
use App\Modules\Stock\Models\ExternalPaymentIntent;
use App\Modules\Stock\Models\Stock;
use App\Modules\Stock\Pricing\FiatQuoteSnapshot;
use App\Modules\Stock\Settlement\SettlementChannel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExternalCapitalBidHttpFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->bindProviders();
        $this->configureReadyIrrState();
    }

    public function test_authenticated_external_bid_checkout_redirects_to_provider_and_never_accepts_manual_fiat_amount(): void
    {
        $user = User::factory()->create();

        // Route model binding may hand the controller an Auction instance. Keep a deliberately
        // inactive lower-id auction so an accidental object-to-int cast cannot silently pass.
        $decoyAuction = $this->auction();
        $decoyAuction->update(['status' => 'settled']);

        $auction = $this->auction();
        $persistedAuction = $auction->fresh();

        $this->assertNotNull($persistedAuction);
        $this->assertSame('running', $persistedAuction->status);
        $this->assertTrue($persistedAuction->ends_at->isFuture(), 'Persisted auction end time must remain in the future before HTTP checkout.');
        $this->assertTrue($persistedAuction->isActive(), 'Persisted auction must be active before HTTP checkout begins.');

        $response = $this->actingAs($user)->post("/auctions/{$auction->id}/external-checkout", [
            'price_gol' => 120,
            'quantity' => 2,
            'checkout_key' => 'browser-checkout-1',
            'amount_irr' => 1,
        ]);

        $intent = ExternalPaymentIntent::query()->firstOrFail();
        $response->assertRedirect('https://payments.example/checkout/' . $intent->provider_intent_id);
        $this->assertSame(240, (int) data_get($intent->quote_snapshot, 'gol_amount'));
        $this->assertNotSame(1, (int) $intent->amount_minor, 'Client supplied fiat amount must never become authoritative.');
        $this->assertDatabaseHas('bids', [
            'auction_id' => $auction->id,
            'user_id' => $user->id,
            'price_gol' => 120,
            'quantity' => 2,
            'status' => 'awaiting_funding',
            'external_payment_intent_id' => $intent->id,
        ]);
    }

    public function test_provider_callback_uses_intent_query_key_and_activates_verified_bid_without_user_session(): void
    {
        $user = User::factory()->create();
        $auction = $this->auction();
        $this->actingAs($user)->post("/auctions/{$auction->id}/external-checkout", [
            'price_gol' => 120,
            'quantity' => 2,
            'checkout_key' => 'browser-checkout-2',
        ])->assertRedirect();
        auth()->logout();

        $intent = ExternalPaymentIntent::query()->firstOrFail();
        $response = $this->get('/stock/external-payment/callback?' . http_build_query([
            'intent' => $intent->intent_key,
            'Status' => 'OK',
            'Authority' => $intent->provider_intent_id,
        ]));

        $response->assertRedirect(route('auction.show', $auction));
        $this->assertDatabaseHas('bids', [
            'auction_id' => $auction->id,
            'external_payment_intent_id' => $intent->id,
            'status' => 'active',
        ]);
        $this->assertSame('confirmed', $intent->fresh()->status);
    }

    public function test_cancelled_provider_callback_persists_cancellation_and_never_activates_bid(): void
    {
        $user = User::factory()->create();
        $auction = $this->auction();
        $this->actingAs($user)->post("/auctions/{$auction->id}/external-checkout", [
            'price_gol' => 120,
            'quantity' => 2,
            'checkout_key' => 'browser-checkout-cancelled',
        ])->assertRedirect();
        auth()->logout();

        $intent = ExternalPaymentIntent::query()->firstOrFail();
        $response = $this->get('/stock/external-payment/callback?' . http_build_query([
            'intent' => $intent->intent_key,
            'Status' => 'NOK',
            'Authority' => $intent->provider_intent_id,
        ]));

        $response->assertRedirect(route('auction.show', $auction));
        $this->assertSame('cancelled', $intent->fresh()->status);
        $this->assertDatabaseHas('bids', [
            'auction_id' => $auction->id,
            'external_payment_intent_id' => $intent->id,
            'status' => 'awaiting_funding',
        ]);
        $this->assertDatabaseMissing('bids', [
            'auction_id' => $auction->id,
            'external_payment_intent_id' => $intent->id,
            'status' => 'active',
        ]);
    }

    private function configureReadyIrrState(): void
    {
        config()->set('stock.external_capital.enabled', true);
        config()->set('stock.external_capital.enabled_currencies', ['IRR']);
        config()->set('stock.external_capital.authoritative_quote_sources', ['fake-rate']);
        config()->set('stock.external_capital.quote_max_age_seconds', 600);
        config()->set('stock.external_capital.quote_future_tolerance_seconds', 30);
        foreach ([
            'rate_provider_uat_passed', 'payment_provider_uat_passed', 'refund_reversal_gameday_passed',
            'offering_policy_validated', 'stock_regression_passed', 'najm_bahar_regression_passed',
            'full_validation_passed', 'founder_rollout_approved',
        ] as $flag) config()->set("stock.external_capital.readiness.{$flag}", true);
        config()->set('stock.primary_offering.max_allocation_bps', 1000);
        config()->set('stock.primary_offering.policy_version', 'earthcoop-primary-v1');
        config()->set('stock.primary_offering.disclosure_version', 'earthcoop-primary-disclosure-v1');
    }

    private function bindProviders(): void
    {
        $this->app->instance(AuthoritativeRateProvider::class, new class implements AuthoritativeRateProvider {
            public function sourceIdentifier(): string { return 'fake-rate'; }
            public function quote(int $golAmount, string $currency): FiatQuoteSnapshot
            { return FiatQuoteSnapshot::fromRate($golAmount, $currency, 500, 1, $this->sourceIdentifier()); }
        });
        $this->app->instance(ExternalPaymentProvider::class, new class implements ExternalPaymentProvider {
            public function providerIdentifier(): string { return 'fake-psp'; }
            public function createIntent(ExternalPaymentIntent $intent): ProviderPaymentIntent
            {
                $id = 'fake-' . $intent->id;
                return new ProviderPaymentIntent($id, $intent->currency, (int) $intent->amount_minor, [
                    'redirect_url' => 'https://payments.example/checkout/' . $id,
                ]);
            }
            public function verifyWebhook(ExternalPaymentIntent $intent, string $payload, array $headers = []): VerifiedPaymentEvent
            {
                parse_str($payload, $callback);
                if ((string) ($callback['Authority'] ?? '') !== (string) $intent->provider_intent_id) {
                    throw new \RuntimeException('Authority mismatch.');
                }
                $cancelled = strtoupper((string) ($callback['Status'] ?? '')) !== 'OK';
                return new VerifiedPaymentEvent(
                    'fake-http-event-' . $intent->id . ($cancelled ? '-cancelled' : '-confirmed'),
                    $cancelled ? 'payment_cancelled' : 'payment_confirmed',
                    $cancelled ? 'cancelled' : 'confirmed',
                    (int) $intent->amount_minor,
                    (string) $intent->currency,
                    $cancelled ? null : 'fake-ref-' . $intent->id,
                    ['authority' => $callback['Authority'] ?? null, 'status' => $callback['Status'] ?? null],
                    ['verification' => $cancelled ? 'cancelled' : 'verified']
                );
            }
        });
    }

    private function auction(): Auction
    {
        $stock = Stock::create([
            'issuer_type' => 'earthcoop', 'startup_valuation' => 1000000,
            'startup_valuation_gol' => 100000000, 'total_shares' => 100000000,
            'available_shares' => 1000000, 'base_share_price' => 0.01, 'base_share_price_gol' => 1,
        ]);
        return Auction::create([
            'stock_id' => $stock->id, 'market_type' => 'primary', 'supply_source' => 'treasury',
            'settlement_channel' => SettlementChannel::EXTERNAL_IRR, 'quote_unit' => 'gol',
            'shares_count' => 1000, 'base_price' => 1, 'base_price_gol' => 100,
            'start_time' => now()->subMinute(), 'ends_at' => now()->addDay(), 'status' => 'running',
            'type' => 'uniform_price', 'lot_size' => 100,
        ]);
    }
}
