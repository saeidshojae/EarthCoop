<?php

return [
    'external_capital' => [
        // Rollout is fail-closed. Enabling the flag alone is never sufficient: the readiness gate also requires provider evidence, UAT attestations and explicit founder approval.
        'enabled' => filter_var(env('STOCK_EXTERNAL_CAPITAL_ENABLED', false), FILTER_VALIDATE_BOOL),

        // Isolated provider UAT is a separate non-production-only boundary. It never makes production rollout ready.
        'uat' => [
            'enabled' => filter_var(env('STOCK_EXTERNAL_UAT_ENABLED', false), FILTER_VALIDATE_BOOL),
        ],

        // Currency rollout is independently fail-closed. The first production/UAT rail is IRR; USD remains disabled unless it is explicitly attested and enabled later.
        'enabled_currencies' => array_values(array_unique(array_filter(array_map(
            static fn (string $currency): string => strtoupper(trim($currency)),
            explode(',', (string) env('STOCK_EXTERNAL_ENABLED_CURRENCIES', ''))
        )))),

        // Provider selection is also fail-closed. Unknown or omitted selectors resolve to unavailable adapters.
        'rate_provider' => trim((string) env('STOCK_EXTERNAL_RATE_PROVIDER', 'unavailable')),
        'payment_provider' => trim((string) env('STOCK_EXTERNAL_PAYMENT_PROVIDER', 'unavailable')),

        // Fail closed by default. Production/UAT must explicitly declare trusted rate-source identifiers.
        'authoritative_quote_sources' => array_values(array_filter(array_map(
            static fn (string $source): string => trim($source),
            explode(',', (string) env('STOCK_EXTERNAL_QUOTE_SOURCES', ''))
        ))),
        'quote_max_age_seconds' => (int) env('STOCK_EXTERNAL_QUOTE_MAX_AGE_SECONDS', 300),
        'quote_future_tolerance_seconds' => (int) env('STOCK_EXTERNAL_QUOTE_FUTURE_TOLERANCE_SECONDS', 30),

        'providers' => [
            'servix' => [
                'base_url' => trim((string) env('STOCK_SERVIX_BASE_URL', 'https://servix.cc/api/v1')),
                'api_key' => trim((string) env('STOCK_SERVIX_API_KEY', '')),
                'timeout_seconds' => (int) env('STOCK_SERVIX_TIMEOUT_SECONDS', 8),
            ],
            'zarinpal' => [
                'base_url' => trim((string) env('STOCK_ZARINPAL_BASE_URL', 'https://api.zarinpal.com/pg/v4')),
                'gateway_url' => trim((string) env('STOCK_ZARINPAL_GATEWAY_URL', 'https://www.zarinpal.com/pg/StartPay')),
                'merchant_id' => trim((string) env('STOCK_ZARINPAL_MERCHANT_ID', '')),
                'callback_url' => trim((string) env('STOCK_ZARINPAL_CALLBACK_URL', '')),
                'description' => trim((string) env('STOCK_ZARINPAL_DESCRIPTION', 'EarthCoop primary treasury share purchase')),
                'timeout_seconds' => (int) env('STOCK_ZARINPAL_TIMEOUT_SECONDS', 8),
            ],
        ],

        'readiness' => [
            'rate_provider_uat_passed' => filter_var(env('STOCK_EXTERNAL_RATE_PROVIDER_UAT_PASSED', false), FILTER_VALIDATE_BOOL),
            'payment_provider_uat_passed' => filter_var(env('STOCK_EXTERNAL_PAYMENT_PROVIDER_UAT_PASSED', false), FILTER_VALIDATE_BOOL),
            'refund_reversal_gameday_passed' => filter_var(env('STOCK_EXTERNAL_REFUND_REVERSAL_GAMEDAY_PASSED', false), FILTER_VALIDATE_BOOL),
            'offering_policy_validated' => filter_var(env('STOCK_EXTERNAL_OFFERING_POLICY_VALIDATED', false), FILTER_VALIDATE_BOOL),
            'stock_regression_passed' => filter_var(env('STOCK_EXTERNAL_STOCK_REGRESSION_PASSED', false), FILTER_VALIDATE_BOOL),
            'najm_bahar_regression_passed' => filter_var(env('STOCK_EXTERNAL_NAJM_BAHAR_REGRESSION_PASSED', false), FILTER_VALIDATE_BOOL),
            'full_validation_passed' => filter_var(env('STOCK_EXTERNAL_FULL_VALIDATION_PASSED', false), FILTER_VALIDATE_BOOL),
            'founder_rollout_approved' => filter_var(env('STOCK_EXTERNAL_FOUNDER_ROLLOUT_APPROVED', false), FILTER_VALIDATE_BOOL),
        ],
    ],

    'primary_offering' => [
        // Canonical funding plan: at most 10% of EarthCoop total shares may leave treasury through primary allocation.
        'max_allocation_bps' => (int) env('STOCK_PRIMARY_OFFERING_MAX_BPS', 1000),
        'policy_version' => trim((string) env('STOCK_PRIMARY_OFFERING_POLICY_VERSION', 'earthcoop-primary-v1')),
        'disclosure_version' => trim((string) env('STOCK_PRIMARY_DISCLOSURE_VERSION', 'earthcoop-primary-disclosure-v1')),
    ],

    'secondary_market' => [
        // Independent rollout boundary. Secondary trading must remain disabled until its own Active Bahar/UAT readiness is completed.
        'enabled' => filter_var(env('STOCK_SECONDARY_MARKET_ENABLED', false), FILTER_VALIDATE_BOOL),
    ],
];
