<?php

use Illuminate\Contracts\Console\Kernel;

require __DIR__ . '/../vendor/autoload.php';

$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$failures = 0;
$warnings = 0;

function report_status(string $label, bool $ok, string $detail = ''): void
{
    $status = $ok ? 'PASS' : 'FAIL';
    $line = $detail === '' ? $label : $label . ' - ' . $detail;
    echo sprintf("[%s] %s\n", $status, $line);
}

function report_warning(string $label, string $detail = ''): void
{
    $line = $detail === '' ? $label : $label . ' - ' . $detail;
    echo sprintf("[WARN] %s\n", $line);
}

$provider = (string) config('najm-hoda.provider.type');
$apiKey = (string) config('najm-hoda.provider.api_key');
$enabled = (bool) config('najm-hoda.enabled');
$mockMode = (bool) config('najm-hoda.mock_mode', false);
$knowledgePath = (string) config('najm-hoda.knowledge_base_path');
$logPath = storage_path('logs');

$validProviders = ['openai', 'openrouter', 'claude', 'gemini'];

$ok = in_array($provider, $validProviders, true);
report_status('AI provider is valid', $ok, $provider);
if (!$ok) {
    $failures++;
}

report_status('NajmHoda enabled', $enabled);
if (!$enabled) {
    $failures++;
}

if ($apiKey === '') {
    report_warning('AI API key is missing', 'system will use mock responses');
    $warnings++;
}

if ($mockMode && $apiKey !== '') {
    report_warning('Mock mode is enabled', 'disable NAJM_HODA_MOCK_MODE for real calls');
    $warnings++;
}

$ok = is_dir($knowledgePath) || @mkdir($knowledgePath, 0755, true);
report_status('Knowledge base path exists', $ok, $knowledgePath);
if (!$ok) {
    $failures++;
}

$ok = is_dir($logPath) && is_writable($logPath);
report_status('Log path writable', $ok, $logPath);
if (!$ok) {
    $failures++;
}

$router = app('router');
$routes = $router->getRoutes();

$hasWelcome = false;
$hasChat = false;
foreach ($routes as $route) {
    $uri = ltrim($route->uri(), '/');
    if ($uri === 'api/najm-hoda/welcome') {
        $hasWelcome = true;
    }
    if ($uri === 'api/najm-hoda/chat') {
        $hasChat = true;
    }
}

report_status('API route /api/najm-hoda/welcome', $hasWelcome);
if (!$hasWelcome) {
    $failures++;
}

report_status('API route /api/najm-hoda/chat', $hasChat);
if (!$hasChat) {
    $failures++;
}

echo "\n";
echo sprintf("Checks complete. Failures: %d, Warnings: %d\n", $failures, $warnings);

exit($failures > 0 ? 1 : 0);
