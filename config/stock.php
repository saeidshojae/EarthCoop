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
];
