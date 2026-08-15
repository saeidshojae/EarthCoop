<?php

namespace App\Services\NajmHoda\Integrations\N8n;

class N8nReadinessService
{
    public function __construct(protected N8nRuntimeControlService $controls)
    {
    }

    /** @return array<string, mixed> */
    public function report(): array
    {
        $baseUrl = trim((string) config('najm-hoda-n8n.base_url'));
        $secret = (string) config('najm-hoda-n8n.shared_secret');
        $cacheDriver = (string) config('cache.default');
        $persistentDrivers = config('najm-hoda-n8n.callback_persistent_cache_drivers', ['redis', 'memcached', 'database']);
        $persistentDrivers = is_array($persistentDrivers) ? $persistentDrivers : [];
        $workflows = config('najm-hoda-n8n.allowed_workflows', []);
        $workflows = is_array($workflows) ? $workflows : [];
        $runtime = $this->controls->state();

        $checks = [
            'integration_config_enabled' => (bool) config('najm-hoda-n8n.enabled', false),
            'base_url_configured' => $baseUrl !== '',
            'base_url_https' => $baseUrl !== '' && str_starts_with(strtolower($baseUrl), 'https://'),
            'shared_secret_configured' => trim($secret) !== '',
            'shared_secret_minimum_length' => strlen($secret) >= 32,
            'persistent_cache' => in_array($cacheDriver, $persistentDrivers, true),
            'callback_config_enabled' => (bool) config('najm-hoda-n8n.callback_http_enabled', false),
            'runtime_outbound_enabled' => (bool) ($runtime['outbound_enabled'] ?? false),
            'runtime_callback_enabled' => (bool) ($runtime['callback_ingress_enabled'] ?? false),
            'allow_list_non_empty' => count($workflows) > 0,
            'allow_list_has_no_apply' => count(array_filter($workflows, static fn ($mode): bool => $mode === 'apply')) === 0,
        ];

        $stagingReady = $checks['integration_config_enabled']
            && $checks['base_url_configured']
            && $checks['base_url_https']
            && $checks['shared_secret_configured']
            && $checks['shared_secret_minimum_length']
            && $checks['persistent_cache']
            && $checks['allow_list_non_empty']
            && $checks['allow_list_has_no_apply'];

        return [
            'status' => $stagingReady ? 'ready' : 'not_ready',
            'checks' => $checks,
            'cache_driver' => $cacheDriver,
            'base_url_host' => $baseUrl !== '' ? (parse_url($baseUrl, PHP_URL_HOST) ?: null) : null,
            'secret_configured' => trim($secret) !== '',
            'secret_length_ok' => strlen($secret) >= 32,
            'callback_http_config_enabled' => (bool) config('najm-hoda-n8n.callback_http_enabled', false),
            'runtime' => $runtime,
            'workflows' => $workflows,
        ];
    }
}
