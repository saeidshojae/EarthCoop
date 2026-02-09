<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "SUPPORT_EMAIL=" . env('SUPPORT_EMAIL') . "\n";
echo "QUEUE_CONNECTION=" . env('QUEUE_CONNECTION') . "\n";
echo "NAJM_HODA_TOKEN=" . env('NAJM_HODA_TOKEN') . "\n";
