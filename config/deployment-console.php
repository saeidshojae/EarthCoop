<?php

return [
    'enabled' => (bool) env('DEPLOYMENT_CONSOLE_ENABLED', false),
    'secret' => env('DEPLOYMENT_CONSOLE_SECRET'),
];
