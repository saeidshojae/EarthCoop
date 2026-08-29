<?php

namespace Tests\Feature\Stock;

use App\Modules\Stock\ExternalCapital\Contracts\ExternalPaymentProvider;
use App\Modules\Stock\ExternalCapital\Contracts\AuthoritativeRateProvider;
use App\Modules\Stock\ExternalCapital\Dto\ProviderPaymentIntent;
use App\Modules\Stock\ExternalCapital\Dto\VerifiedPaymentEvent;
use App\Modules\Stock\ExternalCapital\Services\ExternalCapitalProviderOrchestrator;
use App\Modules\Stock\Models\Auction;
use App\Modules\Stock\Models\ExternalPaymentIntent;
use App\Modules\Stock\Models\Stock;
use App\Modules\Stock\Pricing\FiatQuoteSnapshot;
use App\Modules\Stock\Settlement\SettlementChannel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class ExternalCapitalProviderBoundaryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('stock.external_capital.authoritative_quote_sources', ['fake-rate']);
        config()->set('stock.external_capital.quote_max_age_seconds', 600);
        config()->set('stock.external_capital.quote_future_tolerance_seconds', 30);
    }

    public function test_default_rate_provider_fails_closed(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('rate provider');

        app(AuthoritativeRateProvider::class)->quote(1000, 'USD');
    }

    public function test_default_payment_provider_fails_closed(): void
    {
        $intent = ExternalPaymentIntent::make([
            'intent_key' => 'unpersisted',
            'currency' => 'USD',
            'amount_minor' => 100,
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('payment provider');

        app(ExternalPaymentProvider::class)->createIntent($intent);
    }

    public function test_orchestrator_fails_closed_when_rollout_readiness_is_incomplete(): void
    {
        $this->bindReadyProviders();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('external_capital_disabled');

        app(ExternalCapitalProviderOrchestrator::class)->createPaymentIntentForAuction(
            $this->auction(), 1000, 'USD', 'intent:provider-boundary:not-ready', 'auction_bid', 500
        );
    }

    public function test_orchestrator_binds_authoritative_quote_and_provider_intent_without_crediting_bahar(): void
    {
        $this->bindReadyProviders();
        $this->configureReadyState();

        $intent = app(ExternalCapitalProviderOrchestrator::class)->createPaymentIntentForAuction(
            $this->auction(), 1000, 'USD', 'intent:provider-boundary:1', 'auction_bid', 501
        );

        $this->assertSame(ExternalPaymentIntent::PENDING, $intent->status);
        $this->assertSame('fake-psp', $intent->provider);
        $this->assertSame('psp-intent:provider-boundary:1', $intent->provider_intent_id);
        $this->assertSame('fake-rate', data_get($intent->quote_snapshot, 'source'));
        $this->assertSame(12500, (int) $intent->amount_minor);
    }

    public function test_orchestrator_rejects_rate_provider_identifier_that_does_not_match_snapshot_source(): void
    {
        $this->app->instance(AuthoritativeRateProvider::class, new class implements AuthoritativeRateProvider {
            public function sourceIdentifier(): string { return 'fake-rate'; }
            public function quote(int $golAmount, string $currency): FiatQuoteSnapshot
            {
                return FiatQuoteSnapshot::fromRate($golAmount, $currency, 25, 2, 'spoofed-rate');
            }
        });
        $this->bindReadyPaymentProvider();
        $this->configureReadyState();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('source identifier');

        app(ExternalCapitalProviderOrchestrator::class)->createPaymentIntentForAuction(
            $this->auction(), 1000, 'USD', 'intent:provider-boundary:spoof', 'auction_bid', 502
        );
    }

    public function test_verified_webhook_is_reconciled_through_existing_domain_lifecycle(): void
    {
        $this->app->instance(AuthoritativeRateProvider::class, new class implements AuthoritativeRateProvider {
            public function sourceIdentifier(): string { return 'fake-rate'; }
            public function quote(int $golAmount, string $currency): FiatQuoteSnapshot
            {
                return FiatQuoteSnapshot::fromRate($golAmount, $currency, 25, 2, $this->sourceIdentifier());
            }
        });
        $this->app->instance(ExternalPaymentProvider::class, new class implements ExternalPaymentProvider {
            public function providerIdentifier(): string { return 'fake-psp'; }
            public function createIntent(ExternalPaymentIntent $intent): ProviderPaymentIntent
            {
                return new ProviderPaymentIntent('psp-' . $intent->intent_key, $intent->currency, (int) $intent->amount_minor);
            }
            public function verifyWebhook(ExternalPaymentIntent $intent, string $payload, array $headers = []): VerifiedPaymentEvent
            {
                return new VerifiedPaymentEvent(
                    'provider-event-1', 'payment_confirmed', 'confirmed', 12500, 'USD',
                    'provider-payment-1', ['receipt' => 'SAFE-1']
                );
            }
        });
        $this->configureReadyState();

        $orchestrator = app(ExternalCapitalProviderOrchestrator::class);
        $intent = $orchestrator->createPaymentIntentForAuction(
            $this->auction(), 1000, 'USD', 'intent:provider-boundary:webhook', 'auction_bid', 503
        );

        $event = $orchestrator->reconcileVerifiedWebhook($intent->intent_key, '{}', ['X-Signature' => 'valid']);

        $this->assertSame('confirmed', $event->result_status);
        $this->assertSame(ExternalPaymentIntent::CONFIRMED, $intent->fresh()->status);
        $this->assertSame('provider-event-1', $event->provider_event_id);
    }

    private function bindReadyProviders(): void
    {
        $this->app->instance(AuthoritativeRateProvider::class, new class implements AuthoritativeRateProvider {
            public function sourceIdentifier(): string { return 'fake-rate'; }
            public function quote(int $golAmount, string $currency): FiatQuoteSnapshot
            {
                return FiatQuoteSnapshot::fromRate($golAmount, $currency, 25, 2, $this->sourceIdentifier());
            }
        });
        $this->bindReadyPaymentProvider();
    }

    private function bindReadyPaymentProvider(): void
    {
        $this->app->instance(ExternalPaymentProvider::class, new class implements ExternalPaymentProvider {
            public function providerIdentifier(): string { return 'fake-psp'; }
            public function createIntent(ExternalPaymentIntent $intent): ProviderPaymentIntent
            {
                return new ProviderPaymentIntent('psp-' . $intent->intent_key, $intent->currency, (int) $intent->amount_minor, ['safe_reference' => 'R-1']);
            }
            public function verifyWebhook(ExternalPaymentIntent $intent, string $payload, array $headers = []): VerifiedPaymentEvent
            {
                throw new RuntimeException('unused');
            }
        });
    }

    private function configureReadyState(): void
    {
        config()->set('stock.external_capital.enabled', true);
        config()->set('stock.external_capital.enabled_currencies', ['USD']);
        config()->set('stock.external_capital.readiness.rate_provider_uat_passed', true);
        config()->set('stock.external_capital.readiness.payment_provider_uat_passed', true);
        config()->set('stock.external_capital.readiness.refund_reversal_gameday_passed', true);
        config()->set('stock.external_capital.readiness.offering_policy_validated', true);
        config()->set('stock.external_capital.readiness.stock_regression_passed', true);
        config()->set('stock.external_capital.readiness.najm_bahar_regression_passed', true);
        config()->set('stock.external_capital.readiness.full_validation_passed', true);
        config()->set('stock.external_capital.readiness.founder_rollout_approved', true);
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
