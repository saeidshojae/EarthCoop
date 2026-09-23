<?php

return [
    // Never expose the isolated staging catalog by simply deploying its schema.
    // Turn on only after the complete isolated import and local UAT.
    'enabled' => env('IR_SETTLEMENT_CATALOG_ENABLED', false),
    // Independent, explicitly gated intake; no automatic primary residence or group assignment.
    'claims_enabled' => env('IR_SETTLEMENT_CLAIMS_ENABLED', false),
];
