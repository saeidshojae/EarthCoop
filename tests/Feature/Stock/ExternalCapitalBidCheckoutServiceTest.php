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
use App\Modules\Stock\Models\Wallet;
use App\Modules\Stock\Pricing\FiatQuoteSnapshot;
use App\Modules\Stock\Services\ExternalCapitalBidCheckoutService;
use App\Modules\Stock\Settlement\SettlementChannel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExternalCapitalBidCheckoutServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->bindProviders();
        $this->configureReadyIrrState();
    }

    public function test_checkout_quotes_server_side_links_provider_intent_and_returns_redirect_without_stock_wallet(): void
    {
        $user = User::factory()->create();
        $auction = $this->auction();

        $checkout = app(ExternalCapitalBidCheckoutService::class)->begin(
            $user->id,
            $auction,
            120,
            2,
            'checkout:external:1'
        );

        $this->assertSame('awaiting_funding', $checkout->bid->status);
        $this->assertSame(120, (int) $checkout->bid->price_gol);
        $this->assertSame(2, (int) $checkout->bid->quantity);
        $this->assertSame('https://payments.example/checkout/' . $checkout->paymentIntent->provider_intent_id, $checkout->redirectUrl);
        $this->assertSame(240, (int) data_get($checkout->paymentIntent->quote_snapshot, 'gol_amount'));
        $this->assertSame('pending', $checkout->paymentIntent->status);
        $this->assertSame('fake-psp', $checkout->paymentIntent->provider);
        $this->assertSame($checkout->paymentIntent->id, $checkout->bid->external_payment_intent_id);
        $this->assertSame(0, Wallet::query()->count());
    }

    public function test_verified_callback_activates_only_the_bid_owned_by_the_confirmed_intent(): void
    {
        $user = User::factory()->create();
        $checkout = app(ExternalCapitalBidCheckoutService::class)->begin(
            $user->id,
            $this->auction(),
            120,
            2,
            'checkout:external:2'
        );

        $bid = app(ExternalCapitalBidCheckoutService::class)->handleCallback(
            $checkout->paymentIntent->intent_key,
            'Status=OK&Authority=' . urlencode((string) $checkout->paymentIntent->provider_intent_id)
        );

        $this->assertSame('active', $bid->status);
        $this->assertSame($checkout->bid->id, $bid->id);
        $this->assertSame('confirmed', $checkout->paymentIntent->fresh()->status);
        $this->assertSame(0, Wallet::query()->count());
    }

    private function configureReadyIrrState(): void
    {
        config()->set('stock.external_capital.enabled', true);
        config()->set('stock.external_capital.enabled_currencies', ['IRR']);
        config()->set('stock.external_capital.authoritative_quote_sources', ['fake-rate']);
        config()->set('stock.external_capital.quote_max_age_seconds', 600);
        config()->set('stock.external_capital.quote_future_tolerance_seconds', 30);
        config()->set('stock.external_capital.readiness.rate_provider_uat_passed', true);
        config()->set('stock.external_capital.readiness.payment_provider_uat_passed', true);
        config()->set('stock.external_capital.readiness.refund_reversal_gameday_passed', true);
        config()->set('stock.external_capital.readiness.offering_policy_validated', true);
        config()->set('stock.external_capital.readiness.stock_regression_passed', true);
        config()->set('stock.external_capital.readiness.najm_bahar_regression_passed', true);
        config()->set('stock.external_capital.readiness.full_validation_passed', true);
        config()->set('stock.external_capital.readiness.founder_rollout_approved', true);
        config()->set('stock.primary_offering.max_allocation_bps', 1000);
        config()->set('stock.primary_offering.policy_version', 'earthcoop-primary-v1');
        config()->set('stock.primary_offering.disclosure_version', 'earthcoop-primary-disclosure-v1');
    }

    private function bindProviders(): void
    {
        $this->app->instance(AuthoritativeRateProvider::class, new class implements AuthoritativeRateProvider {
            public function sourceIdentifier(): string { return 'fake-rate'; }
            public function quote(int $golAmount, string $currency): FiatQuoteSnapshot
            {
                return FiatQuoteSnapshot::fromRate($golAmount, $currency, 500, 1, $this->sourceIdentifier());
            }
        });

        $this->app->instance(ExternalPaymentProvider::class, new class implements ExternalPaymentProvider {
            public function providerIdentifier(): string { return 'fake-psp'; }
            public function createIntent(ExternalPaymentIntent $intent): ProviderPaymentIntent
            {
                $providerId = 'fake-' . $intent->intent_key;
                return new ProviderPaymentIntent(
                    $providerId,
                    $intent->currency,
                    (int) $intent->amount_minor,
                    ['redirect_url' => 'https://payments.example/checkout/' . $providerId]
                );
            }
            public function verifyWebhook(ExternalPaymentIntent $intent, string $payload, array $headers = []): VerifiedPaymentEvent
            {
                parse_str($payload, $callback);
                $authority = (string) ($callback['Authority'] ?? '');
                if ($authority !== (string) $intent->provider_intent_id) {
                    throw new \RuntimeException('Authority mismatch.');
                }

                return new VerifiedPaymentEvent(
                    'fake-event-' . $intent->intent_key,
                    'payment_confirmed',
                    'confirmed',
                    (int) $intent->amount_minor,
                    (string) $intent->currency,
                    'fake-ref-' . $intent->id,
                    ['authority' => $authority],
                    ['verification' => 'verified']
                );
            }
        });
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
