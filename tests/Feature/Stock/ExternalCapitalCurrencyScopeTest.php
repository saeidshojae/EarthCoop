<?php

namespace Tests\Feature\Stock;

use App\Modules\Stock\ExternalCapital\Contracts\AuthoritativeRateProvider;
use App\Modules\Stock\ExternalCapital\Contracts\ExternalPaymentProvider;
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

class ExternalCapitalCurrencyScopeTest extends TestCase
{
    use RefreshDatabase;

    public function test_usd_is_blocked_before_provider_call_when_rollout_enables_only_irr(): void
    {
        $this->bindReadyProviders();
        $this->configureReadyState();
        config()->set('stock.external_capital.enabled_currencies', ['IRR']);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('external_currency_not_enabled');

        app(ExternalCapitalProviderOrchestrator::class)->createPaymentIntentForAuction(
            $this->auction(SettlementChannel::EXTERNAL_USD),
            1000,
            'USD',
            'intent:currency-scope:usd-blocked',
            'auction_bid',
            9001,
        );
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

        $this->app->instance(ExternalPaymentProvider::class, new class implements ExternalPaymentProvider {
            public function providerIdentifier(): string { return 'fake-psp'; }

            public function createIntent(ExternalPaymentIntent $intent): ProviderPaymentIntent
            {
                return new ProviderPaymentIntent('fake-' . $intent->intent_key, $intent->currency, (int) $intent->amount_minor);
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
        config()->set('stock.external_capital.authoritative_quote_sources', ['fake-rate']);
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

    private function auction(string $settlementChannel): Auction
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
            'settlement_channel' => $settlementChannel,
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
