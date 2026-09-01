<?php

namespace Tests\Feature\Stock;

use App\Modules\Stock\ExternalCapital\Adapters\ServixGold24AuthoritativeRateProvider;
use App\Modules\Stock\ExternalCapital\Adapters\ZarinpalExternalPaymentProvider;
use App\Modules\Stock\ExternalCapital\Contracts\AuthoritativeRateProvider;
use App\Modules\Stock\ExternalCapital\Contracts\ExternalPaymentProvider;
use App\Modules\Stock\ExternalCapital\Dto\ProviderPaymentIntent;
use App\Modules\Stock\ExternalCapital\Dto\VerifiedPaymentEvent;
use App\Modules\Stock\ExternalCapital\Services\ExternalCapitalReadinessGate;
use App\Modules\Stock\Models\ExternalPaymentIntent;
use App\Modules\Stock\Pricing\FiatQuoteSnapshot;
use RuntimeException;
use Tests\TestCase;

class ExternalCapitalReadinessGateTest extends TestCase
{
    public function test_readiness_gate_contract_exists(): void
    {
        $this->assertTrue(class_exists(ExternalCapitalReadinessGate::class));
    }

    public function test_default_configuration_fails_closed_with_explicit_blockers(): void
    {
        $report = app(ExternalCapitalReadinessGate::class)->report();

        $this->assertFalse($report['ready']);
        $this->assertFalse($report['enabled']);
        $this->assertContains('external_capital_disabled', $report['blockers']);
        $this->assertContains('authoritative_rate_provider_unavailable', $report['blockers']);
        $this->assertContains('external_payment_provider_unavailable', $report['blockers']);
        $this->assertContains('founder_rollout_approval_missing', $report['blockers']);
        $this->assertFalse(config('stock.secondary_market.enabled'));
    }

    public function test_enabled_flag_alone_never_makes_external_capital_ready(): void
    {
        config()->set('stock.external_capital.enabled', true);

        $report = app(ExternalCapitalReadinessGate::class)->report();

        $this->assertFalse($report['ready']);
        $this->assertNotContains('external_capital_disabled', $report['blockers']);
        $this->assertNotEmpty($report['blockers']);
    }

    public function test_gate_is_ready_only_when_runtime_evidence_and_rollout_attestations_are_complete(): void
    {
        $this->bindReadyProviders();
        $this->configureReadyState();

        $report = app(ExternalCapitalReadinessGate::class)->report();

        $this->assertTrue($report['enabled']);
        $this->assertTrue($report['ready']);
        $this->assertSame([], $report['blockers']);
        $this->assertTrue($report['checks']['authoritative_rate_provider']);
        $this->assertTrue($report['checks']['external_payment_provider']);
        $this->assertTrue($report['checks']['authoritative_rate_provider_configuration']);
        $this->assertTrue($report['checks']['external_payment_provider_configuration']);
        $this->assertTrue($report['checks']['full_validation']);
        $this->assertTrue($report['checks']['founder_rollout_approval']);
    }

    public function test_real_servix_adapter_is_blocked_when_its_runtime_configuration_is_incomplete(): void
    {
        $this->configureReadyState();
        config()->set('stock.external_capital.authoritative_quote_sources', ['servix:gold24:irr:v1']);
        config()->set('stock.external_capital.providers.servix.api_key', '');
        config()->set('stock.external_capital.providers.servix.base_url', 'https://servix.cc/api/v1');
        $this->app->instance(AuthoritativeRateProvider::class, new ServixGold24AuthoritativeRateProvider());
        $this->app->instance(ExternalPaymentProvider::class, $this->fakePaymentProvider());

        $report = app(ExternalCapitalReadinessGate::class)->report();

        $this->assertFalse($report['ready']);
        $this->assertTrue($report['checks']['authoritative_rate_provider']);
        $this->assertFalse($report['checks']['authoritative_rate_provider_configuration']);
        $this->assertContains('authoritative_rate_provider_configuration_invalid', $report['blockers']);
    }

    public function test_real_zarinpal_adapter_is_blocked_when_callback_is_not_canonical(): void
    {
        $this->configureReadyState();
        $this->app->instance(AuthoritativeRateProvider::class, $this->fakeRateProvider());
        $this->app->instance(ExternalPaymentProvider::class, new ZarinpalExternalPaymentProvider());
        config()->set('stock.external_capital.providers.zarinpal.merchant_id', 'merchant-test');
        config()->set('stock.external_capital.providers.zarinpal.base_url', 'https://api.zarinpal.com/pg/v4');
        config()->set('stock.external_capital.providers.zarinpal.gateway_url', 'https://www.zarinpal.com/pg/StartPay');
        config()->set('stock.external_capital.providers.zarinpal.callback_url', 'https://earthcoop.ir/wrong/callback');
        config()->set('stock.external_capital.providers.zarinpal.description', 'EarthCoop primary treasury share purchase');

        $report = app(ExternalCapitalReadinessGate::class)->report();

        $this->assertFalse($report['ready']);
        $this->assertTrue($report['checks']['external_payment_provider']);
        $this->assertFalse($report['checks']['external_payment_provider_configuration']);
        $this->assertContains('external_payment_provider_configuration_invalid', $report['blockers']);
    }

    public function test_real_servix_and_zarinpal_configuration_can_pass_without_exposing_secrets_in_evidence(): void
    {
        $this->configureReadyState();
        $this->configureRealProviders();

        $report = app(ExternalCapitalReadinessGate::class)->report();

        $this->assertTrue($report['checks']['authoritative_rate_provider_configuration']);
        $this->assertTrue($report['checks']['external_payment_provider_configuration']);
        $this->assertTrue($report['ready']);
        $evidence = json_encode($report['evidence'], JSON_THROW_ON_ERROR);
        $this->assertStringNotContainsString('servix-secret-key', $evidence);
        $this->assertStringNotContainsString('merchant-secret-id', $evidence);
    }

    public function test_uat_readiness_is_fail_closed_and_never_available_in_production(): void
    {
        $this->configureRealProviders();
        config()->set('stock.external_capital.enabled_currencies', ['IRR']);
        config()->set('stock.external_capital.uat.enabled', true);
        $this->app['env'] = 'production';

        $report = app(ExternalCapitalReadinessGate::class)->uatReport();

        $this->assertFalse($report['ready']);
        $this->assertContains('external_uat_forbidden_in_production', $report['blockers']);
    }

    public function test_uat_readiness_allows_real_provider_exercise_without_faking_production_attestations(): void
    {
        $this->configureRealProviders();
        config()->set('stock.external_capital.enabled', false);
        config()->set('stock.external_capital.enabled_currencies', ['IRR']);
        config()->set('stock.external_capital.uat.enabled', true);
        config()->set('stock.external_capital.readiness.rate_provider_uat_passed', false);
        config()->set('stock.external_capital.readiness.payment_provider_uat_passed', false);
        config()->set('stock.external_capital.readiness.founder_rollout_approved', false);
        $this->app['env'] = 'testing';

        $report = app(ExternalCapitalReadinessGate::class)->uatReport();

        $this->assertTrue($report['ready']);
        $this->assertSame([], $report['blockers']);
        $this->assertFalse(config('stock.external_capital.enabled'));
        $this->assertFalse(config('stock.external_capital.readiness.rate_provider_uat_passed'));
        $this->assertFalse(config('stock.external_capital.readiness.payment_provider_uat_passed'));
        $this->assertFalse(config('stock.external_capital.readiness.founder_rollout_approved'));
        app(ExternalCapitalReadinessGate::class)->assertUatReadyForCurrency('IRR');
    }

    public function test_assert_ready_throws_with_blocker_codes_when_not_ready(): void
    {
        try {
            app(ExternalCapitalReadinessGate::class)->assertReady();
            $this->fail('Expected external capital readiness gate to fail closed.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('external_capital_disabled', $e->getMessage());
            $this->assertStringContainsString('founder_rollout_approval_missing', $e->getMessage());
        }
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

    private function configureRealProviders(): void
    {
        config()->set('stock.external_capital.authoritative_quote_sources', ['servix:gold24:irr:v1']);
        config()->set('stock.external_capital.providers.servix.api_key', 'servix-secret-key');
        config()->set('stock.external_capital.providers.servix.base_url', 'https://servix.cc/api/v1');
        config()->set('stock.external_capital.providers.zarinpal.merchant_id', 'merchant-secret-id');
        config()->set('stock.external_capital.providers.zarinpal.base_url', 'https://api.zarinpal.com/pg/v4');
        config()->set('stock.external_capital.providers.zarinpal.gateway_url', 'https://www.zarinpal.com/pg/StartPay');
        config()->set('stock.external_capital.providers.zarinpal.callback_url', 'https://earthcoop.ir/stock/external-payment/callback');
        config()->set('stock.external_capital.providers.zarinpal.description', 'EarthCoop primary treasury share purchase');
        config()->set('stock.primary_offering.max_allocation_bps', 1000);
        config()->set('stock.primary_offering.policy_version', 'earthcoop-primary-v1');
        config()->set('stock.primary_offering.disclosure_version', 'earthcoop-primary-disclosure-v1');
        $this->app->instance(AuthoritativeRateProvider::class, new ServixGold24AuthoritativeRateProvider());
        $this->app->instance(ExternalPaymentProvider::class, new ZarinpalExternalPaymentProvider());
    }

    private function bindReadyProviders(): void
    {
        $this->app->instance(AuthoritativeRateProvider::class, $this->fakeRateProvider());
        $this->app->instance(ExternalPaymentProvider::class, $this->fakePaymentProvider());
    }

    private function fakeRateProvider(): AuthoritativeRateProvider
    {
        return new class implements AuthoritativeRateProvider {
            public function sourceIdentifier(): string { return 'fake-rate'; }
            public function quote(int $golAmount, string $currency): FiatQuoteSnapshot
            {
                return FiatQuoteSnapshot::fromRate($golAmount, $currency, 25, 2, $this->sourceIdentifier());
            }
        };
    }

    private function fakePaymentProvider(): ExternalPaymentProvider
    {
        return new class implements ExternalPaymentProvider {
            public function providerIdentifier(): string { return 'fake-psp'; }
            public function createIntent(ExternalPaymentIntent $intent): ProviderPaymentIntent
            {
                return new ProviderPaymentIntent('fake-' . $intent->intent_key, $intent->currency, (int) $intent->amount_minor);
            }
            public function verifyWebhook(ExternalPaymentIntent $intent, string $payload, array $headers = []): VerifiedPaymentEvent
            {
                throw new RuntimeException('unused');
            }
        };
    }
}
