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
use App\Modules\Stock\Services\ExternalCapitalBidCheckoutService;
use App\Modules\Stock\Settlement\SettlementChannel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExternalCapitalUatCheckoutServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_begin_uat_can_exercise_checkout_while_production_rollout_remains_disabled(): void
    {
        $this->app['env'] = 'testing';
        config()->set('stock.external_capital.enabled', false);
        config()->set('stock.external_capital.uat.enabled', true);
        config()->set('stock.external_capital.enabled_currencies', ['IRR']);
        config()->set('stock.external_capital.authoritative_quote_sources', ['fake-rate']);
        config()->set('stock.external_capital.readiness.rate_provider_uat_passed', false);
        config()->set('stock.external_capital.readiness.payment_provider_uat_passed', false);
        config()->set('stock.external_capital.readiness.founder_rollout_approved', false);
        config()->set('stock.primary_offering.max_allocation_bps', 1000);
        config()->set('stock.primary_offering.policy_version', 'earthcoop-primary-v1');
        config()->set('stock.primary_offering.disclosure_version', 'earthcoop-primary-disclosure-v1');

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
                $providerId = 'uat-' . $intent->intent_key;
                return new ProviderPaymentIntent($providerId, $intent->currency, (int) $intent->amount_minor, [
                    'redirect_url' => 'https://payments.example/uat/' . $providerId,
                ]);
            }
            public function verifyWebhook(ExternalPaymentIntent $intent, string $payload, array $headers = []): VerifiedPaymentEvent
            {
                throw new \RuntimeException('unused');
            }
        });

        $user = User::factory()->create();
        $checkout = app(ExternalCapitalBidCheckoutService::class)->beginUat(
            $user->id,
            $this->auction(),
            120,
            2,
            'uat:external:1'
        );

        $this->assertSame('awaiting_funding', $checkout->bid->status);
        $this->assertSame('pending', $checkout->paymentIntent->status);
        $this->assertSame('IRR', $checkout->paymentIntent->currency);
        $this->assertStringStartsWith('https://payments.example/uat/', $checkout->redirectUrl);
        $this->assertFalse(config('stock.external_capital.enabled'));
        $this->assertFalse(config('stock.external_capital.readiness.rate_provider_uat_passed'));
        $this->assertFalse(config('stock.external_capital.readiness.payment_provider_uat_passed'));
        $this->assertFalse(config('stock.external_capital.readiness.founder_rollout_approved'));
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
