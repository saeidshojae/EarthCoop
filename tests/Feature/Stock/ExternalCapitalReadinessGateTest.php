<?php

namespace Tests\Feature\Stock;

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
        if (! class_exists(ExternalCapitalReadinessGate::class)) {
            $this->markTestSkipped('Readiness gate not implemented yet.');
        }

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
        if (! class_exists(ExternalCapitalReadinessGate::class)) {
            $this->markTestSkipped('Readiness gate not implemented yet.');
        }

        config()->set('stock.external_capital.enabled', true);

        $report = app(ExternalCapitalReadinessGate::class)->report();

        $this->assertFalse($report['ready']);
        $this->assertNotContains('external_capital_disabled', $report['blockers']);
        $this->assertNotEmpty($report['blockers']);
    }

    public function test_gate_is_ready_only_when_runtime_evidence_and_rollout_attestations_are_complete(): void
    {
        if (! class_exists(ExternalCapitalReadinessGate::class)) {
            $this->markTestSkipped('Readiness gate not implemented yet.');
        }

        $this->bindReadyProviders();
        $this->configureReadyState();

        $report = app(ExternalCapitalReadinessGate::class)->report();

        $this->assertTrue($report['enabled']);
        $this->assertTrue($report['ready']);
        $this->assertSame([], $report['blockers']);
        $this->assertTrue($report['checks']['authoritative_rate_provider']);
        $this->assertTrue($report['checks']['external_payment_provider']);
        $this->assertTrue($report['checks']['full_validation']);
        $this->assertTrue($report['checks']['founder_rollout_approval']);
    }

    public function test_assert_ready_throws_with_blocker_codes_when_not_ready(): void
    {
        if (! class_exists(ExternalCapitalReadinessGate::class)) {
            $this->markTestSkipped('Readiness gate not implemented yet.');
        }

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
            public function verifyWebhook(string $payload, array $headers = []): VerifiedPaymentEvent
            {
                throw new RuntimeException('unused');
            }
        });
    }
}
