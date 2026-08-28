<?php

return [
    'external_capital' => [
        // Fail closed by default. Production/UAT must explicitly declare trusted rate-source identifiers.
        'authoritative_quote_sources' => array_values(array_filter(array_map(
            static fn (string $source): string => trim($source),
            explode(',', (string) env('STOCK_EXTERNAL_QUOTE_SOURCES', ''))
        ))),
        'quote_max_age_seconds' => (int) env('STOCK_EXTERNAL_QUOTE_MAX_AGE_SECONDS', 300),
        'quote_future_tolerance_seconds' => (int) env('STOCK_EXTERNAL_QUOTE_FUTURE_TOLERANCE_SECONDS', 30),
    ],

    'primary_offering' => [
        // Canonical funding plan: at most 10% of EarthCoop total shares may leave treasury through primary allocation.
        'max_allocation_bps' => (int) env('STOCK_PRIMARY_OFFERING_MAX_BPS', 1000),
        'policy_version' => (string) env('STOCK_PRIMARY_OFFERING_POLICY_VERSION', 'earthcoop-primary-v1'),
        'disclosure_version' => (string) env('STOCK_PRIMARY_DISCLOSURE_VERSION', 'earthcoop-primary-disclosure-v1'),
    ],
];
