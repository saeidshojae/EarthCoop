<?php

return [
    // Dark-launch switch for choosing Iran 1404 v2 in live residence/project selectors.
    // Importing v2 data alone must not change Production behavior.
    'v2_runtime_enabled' => filter_var(env('IR_1404_V2_RUNTIME_ENABLED', false), FILTER_VALIDATE_BOOLEAN),
    // Never expose the isolated staging catalog by simply deploying its schema.
    // Turn on only after the complete isolated import and local UAT.
    'enabled' => env('IR_SETTLEMENT_CATALOG_ENABLED', false),
    // Independent, explicitly gated intake; no automatic primary residence or group assignment.
    'claims_enabled' => env('IR_SETTLEMENT_CLAIMS_ENABLED', false),
    'claim_review_threshold' => (int) env('IR_SETTLEMENT_CLAIM_REVIEW_THRESHOLD', 10),
    // Fixed production/UAT contract; tests may override config in-memory only.
    'uat_expected_count' => 99317,
];
