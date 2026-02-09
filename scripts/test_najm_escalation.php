<?php
require __DIR__ . '/../vendor/autoload.php';

// Boot the framework
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Services\NajmHodaIntegrationService;

echo "Running Najm Hoda escalation test...\n";

$service = app(NajmHodaIntegrationService::class);

$payload = [
    'conversation_id' => 'test-najm-'.time(),
    'transcript' => "سلام، این یک تست است. کاربر پرسشی داشت که نیاز به اپراتور دارد.",
    'user_email' => 'test+najm@example.com',
    'reason' => 'can_not_answer',
    'metadata' => ['channel' => 'chat', 'locale' => 'fa'],
];

$ticket = $service->handleEscalation($payload);

echo "Created ticket ID: {$ticket->id}\n";
echo "Tracking code: {$ticket->tracking_code}\n";
