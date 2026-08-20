<?php

return [
    /*
    | Canonical Najm Bahar account that receives Active Bahar proceeds from
    | EarthCoop primary treasury Stock offerings. Keep null in environments
    | where canonical settlement must remain fail-closed.
    */
    'earthcoop_capital_account_number' => env('STOCK_EARTHCOOP_CAPITAL_ACCOUNT_NUMBER'),

    /* External provider/rate source is deliberately disabled until a real
    | provider adapter and trusted quote source are configured. */
    'external_capital_enabled' => (bool) env('STOCK_EXTERNAL_CAPITAL_ENABLED', false),

    /* Secondary market remains disabled for settlement until holder-share
    | reservation / seller settlement identity is fully cut over. */
    'secondary_market_enabled' => (bool) env('STOCK_SECONDARY_MARKET_ENABLED', false),
];
