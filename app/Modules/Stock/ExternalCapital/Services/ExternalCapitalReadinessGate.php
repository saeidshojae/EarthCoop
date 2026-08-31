<?php

namespace App\Modules\Stock\ExternalCapital\Services;

use App\Modules\Stock\ExternalCapital\Contracts\AuthoritativeRateProvider;
use App\Modules\Stock\ExternalCapital\Contracts\ExternalPaymentProvider;
use InvalidArgumentException;
use RuntimeException;

final class ExternalCapitalReadinessGate
{
    private const CANONICAL_CALLBACK_PATH = '/stock/external-payment/callback';

    public function __construct(
        private readonly AuthoritativeRateProvider $rates,
        private readonly ExternalPaymentProvider $payments,
    ) {}

    public function report(): array
    {
        $enabled = (bool) config('stock.external_capital.enabled', false);
        $runtime = $this->runtimeEvidence();

        $checks = [
            'feature_enabled' => $enabled,
            'authoritative_rate_provider' => $runtime['rate_provider_ready'],
            'authoritative_rate_provider_configuration' => $runtime['rate_provider_configuration_ready'],
            'external_payment_provider' => $runtime['payment_provider_ready'],
            'external_payment_provider_configuration' => $runtime['payment_provider_configuration_ready'],
            'primary_offering_configuration' => $runtime['primary_offering_configuration_ready'],
            'rate_provider_uat' => (bool) config('stock.external_capital.readiness.rate_provider_uat_passed', false),
            'payment_provider_uat' => (bool) config('stock.external_capital.readiness.payment_provider_uat_passed', false),
            'refund_reversal_gameday' => (bool) config('stock.external_capital.readiness.refund_reversal_gameday_passed', false),
            'offering_policy_validation' => (bool) config('stock.external_capital.readiness.offering_policy_validated', false),
            'stock_regression' => (bool) config('stock.external_capital.readiness.stock_regression_passed', false),
            'najm_bahar_regression' => (bool) config('stock.external_capital.readiness.najm_bahar_regression_passed', false),
            'full_validation' => (bool) config('stock.external_capital.readiness.full_validation_passed', false),
            'founder_rollout_approval' => (bool) config('stock.external_capital.readiness.founder_rollout_approved', false),
        ];

        $blockerMap = [
            'feature_enabled' => 'external_capital_disabled',
            'authoritative_rate_provider' => 'authoritative_rate_provider_unavailable',
            'authoritative_rate_provider_configuration' => 'authoritative_rate_provider_configuration_invalid',
            'external_payment_provider' => 'external_payment_provider_unavailable',
            'external_payment_provider_configuration' => 'external_payment_provider_configuration_invalid',
            'primary_offering_configuration' => 'primary_offering_configuration_invalid',
            'rate_provider_uat' => 'rate_provider_uat_missing',
            'payment_provider_uat' => 'payment_provider_uat_missing',
            'refund_reversal_gameday' => 'refund_reversal_gameday_missing',
            'offering_policy_validation' => 'offering_policy_validation_missing',
            'stock_regression' => 'stock_regression_missing',
            'najm_bahar_regression' => 'najm_bahar_regression_missing',
            'full_validation' => 'full_validation_missing',
            'founder_rollout_approval' => 'founder_rollout_approval_missing',
        ];

        $blockers = $this->collectBlockers($checks, $blockerMap);

        return [
            'enabled' => $enabled,
            'ready' => $blockers === [],
            'checks' => $checks,
            'blockers' => $blockers,
            'evidence' => $runtime['evidence'],
        ];
    }

    /**
     * Provider-UAT readiness is deliberately distinct from production rollout.
     * It can only run outside production and never relies on rollout attestations
     * that the UAT itself is intended to establish.
     */
    public function uatReport(): array
    {
        $uatEnabled = (bool) config('stock.external_capital.uat.enabled', false);
        $nonProduction = ! app()->environment('production');
        $runtime = $this->runtimeEvidence();
        $enabledCurrencies = $this->enabledCurrencies();

        $checks = [
            'uat_enabled' => $uatEnabled,
            'non_production_environment' => $nonProduction,
            'authoritative_rate_provider' => $runtime['rate_provider_ready'],
            'authoritative_rate_provider_configuration' => $runtime['rate_provider_configuration_ready'],
            'external_payment_provider' => $runtime['payment_provider_ready'],
            'external_payment_provider_configuration' => $runtime['payment_provider_configuration_ready'],
            'primary_offering_configuration' => $runtime['primary_offering_configuration_ready'],
            'external_currency_enabled' => $enabledCurrencies !== [],
            'authoritative_quote_source_configured' => $runtime['allowed_rate_sources'] !== [],
        ];

        $blockerMap = [
            'uat_enabled' => 'external_uat_disabled',
            'non_production_environment' => 'external_uat_forbidden_in_production',
            'authoritative_rate_provider' => 'authoritative_rate_provider_unavailable',
            'authoritative_rate_provider_configuration' => 'authoritative_rate_provider_configuration_invalid',
            'external_payment_provider' => 'external_payment_provider_unavailable',
            'external_payment_provider_configuration' => 'external_payment_provider_configuration_invalid',
            'primary_offering_configuration' => 'primary_offering_configuration_invalid',
            'external_currency_enabled' => 'no_external_currency_enabled',
            'authoritative_quote_source_configured' => 'no_authoritative_quote_source_configured',
        ];

        $blockers = $this->collectBlockers($checks, $blockerMap);

        return [
            'enabled' => $uatEnabled,
            'ready' => $blockers === [],
            'checks' => $checks,
            'blockers' => $blockers,
            'evidence' => array_merge($runtime['evidence'], [
                'environment' => app()->environment(),
                'uat_only' => true,
                'production_feature_enabled' => (bool) config('stock.external_capital.enabled', false),
            ]),
        ];
    }

    public function isReady(): bool
    {
        return $this->report()['ready'];
    }

    public function assertReady(): void
    {
        $this->assertReportReady($this->report(), 'External capital readiness gate blocked operation: ');
    }

    public function assertReadyForCurrency(string $currency): void
    {
        $this->assertReady();
        $this->assertCurrencyEnabled($currency, 'External capital readiness gate blocked operation: ');
    }

    public function assertUatReady(): void
    {
        $this->assertReportReady($this->uatReport(), 'External capital UAT readiness gate blocked operation: ');
    }

    public function assertUatReadyForCurrency(string $currency): void
    {
        $this->assertUatReady();
        $this->assertCurrencyEnabled($currency, 'External capital UAT readiness gate blocked operation: ');
    }

    private function runtimeEvidence(): array
    {
        $rateSource = trim($this->rates->sourceIdentifier());
        $allowedRateSources = array_values(array_filter(array_map(
            static fn ($source): string => trim((string) $source),
            (array) config('stock.external_capital.authoritative_quote_sources', [])
        )));
        $paymentProvider = trim($this->payments->providerIdentifier());
        $enabledCurrencies = $this->enabledCurrencies();
        $maxAllocationBps = (int) config('stock.primary_offering.max_allocation_bps', 0);
        $policyVersion = trim((string) config('stock.primary_offering.policy_version', ''));
        $disclosureVersion = trim((string) config('stock.primary_offering.disclosure_version', ''));

        return [
            'rate_provider_ready' => $rateSource !== ''
                && $rateSource !== 'unavailable'
                && in_array($rateSource, $allowedRateSources, true),
            'rate_provider_configuration_ready' => $this->rateProviderConfigurationReady($rateSource),
            'payment_provider_ready' => $paymentProvider !== '' && $paymentProvider !== 'unavailable',
            'payment_provider_configuration_ready' => $this->paymentProviderConfigurationReady($paymentProvider),
            'primary_offering_configuration_ready' => $maxAllocationBps > 0
                && $maxAllocationBps <= 10000
                && $policyVersion !== ''
                && $disclosureVersion !== '',
            'allowed_rate_sources' => $allowedRateSources,
            'evidence' => [
                'rate_source' => $rateSource,
                'payment_provider' => $paymentProvider,
                'enabled_currencies' => $enabledCurrencies,
                'primary_offering_max_allocation_bps' => $maxAllocationBps,
                'primary_offering_policy_version' => $policyVersion,
                'primary_offering_disclosure_version' => $disclosureVersion,
                'canonical_callback_path' => self::CANONICAL_CALLBACK_PATH,
            ],
        ];
    }

    private function collectBlockers(array $checks, array $blockerMap): array
    {
        $blockers = [];
        foreach ($checks as $check => $passed) {
            if (! $passed) {
                $blockers[] = $blockerMap[$check];
            }
        }

        return $blockers;
    }

    private function assertReportReady(array $report, string $prefix): void
    {
        if ($report['ready']) {
            return;
        }

        throw new RuntimeException($prefix . implode(', ', $report['blockers']));
    }

    private function assertCurrencyEnabled(string $currency, string $prefix): void
    {
        $currency = strtoupper(trim($currency));
        if (! in_array($currency, ['IRR', 'USD'], true)) {
            throw new InvalidArgumentException('External capital currency must be IRR or USD.');
        }

        if (! in_array($currency, $this->enabledCurrencies(), true)) {
            throw new RuntimeException($prefix . 'external_currency_not_enabled');
        }
    }

    private function enabledCurrencies(): array
    {
        return array_values(array_unique(array_filter(array_map(
            static fn ($currency): string => strtoupper(trim((string) $currency)),
            (array) config('stock.external_capital.enabled_currencies', [])
        ), static fn (string $currency): bool => in_array($currency, ['IRR', 'USD'], true))));
    }

    private function rateProviderConfigurationReady(string $rateSource): bool
    {
        if ($rateSource === '' || $rateSource === 'unavailable') {
            return false;
        }

        if ($rateSource !== 'servix:gold24:irr:v1') {
            return true;
        }

        $apiKey = trim((string) config('stock.external_capital.providers.servix.api_key', ''));
        $baseUrl = trim((string) config('stock.external_capital.providers.servix.base_url', ''));

        return $apiKey !== '' && $this->isHttpsUrl($baseUrl);
    }

    private function paymentProviderConfigurationReady(string $paymentProvider): bool
    {
        if ($paymentProvider === '' || $paymentProvider === 'unavailable') {
            return false;
        }

        if ($paymentProvider !== 'zarinpal') {
            return true;
        }

        $merchantId = trim((string) config('stock.external_capital.providers.zarinpal.merchant_id', ''));
        $baseUrl = trim((string) config('stock.external_capital.providers.zarinpal.base_url', ''));
        $gatewayUrl = trim((string) config('stock.external_capital.providers.zarinpal.gateway_url', ''));
        $callbackUrl = trim((string) config('stock.external_capital.providers.zarinpal.callback_url', ''));
        $description = trim((string) config('stock.external_capital.providers.zarinpal.description', ''));

        if ($merchantId === '' || $description === '') {
            return false;
        }
        if (! $this->isHttpsUrl($baseUrl) || ! $this->isHttpsUrl($gatewayUrl) || ! $this->isHttpsUrl($callbackUrl)) {
            return false;
        }

        $callbackPath = parse_url($callbackUrl, PHP_URL_PATH);
        if (! is_string($callbackPath) || rtrim($callbackPath, '/') !== self::CANONICAL_CALLBACK_PATH) {
            return false;
        }

        $callbackQuery = parse_url($callbackUrl, PHP_URL_QUERY);
        if (is_string($callbackQuery) && $callbackQuery !== '') {
            parse_str($callbackQuery, $query);
            if (array_key_exists('intent', $query) || array_key_exists('intent_key', $query)) {
                return false;
            }
        }

        return true;
    }

    private function isHttpsUrl(string $url): bool
    {
        if ($url === '' || filter_var($url, FILTER_VALIDATE_URL) === false) {
            return false;
        }

        return strtolower((string) parse_url($url, PHP_URL_SCHEME)) === 'https';
    }
}
