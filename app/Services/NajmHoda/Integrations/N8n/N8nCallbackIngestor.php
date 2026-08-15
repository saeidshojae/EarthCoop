<?php

namespace App\Services\NajmHoda\Integrations\N8n;

use App\Services\NajmHoda\Runtime\RuntimeEventBus;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class N8nCallbackIngestor
{
    public function __construct(
        protected N8nCallbackVerifier $verifier,
        protected RuntimeEventBus $events,
        protected N8nRuntimeControlService $controls,
    ) {
    }

    /**
     * Verify and persist callback state/result only.
     *
     * This service intentionally has no dependency on capability registries,
     * executors, goal loops, approval services or action dispatchers.
     *
     * @param array<string, string|null> $headers
     * @return array<string, mixed>
     */
    public function ingest(string $rawBody, array $headers): array
    {
        $this->assertHttpIngressAllowed();

        $normalized = $this->verifier->verify($rawBody, $headers);

        try {
            DB::table('najm_hoda_n8n_callbacks')->insert([
                'request_id' => $normalized['request_id'],
                'correlation_id' => $normalized['correlation_id'],
                'workflow' => $normalized['workflow'],
                'mode' => $normalized['mode'],
                'status' => $normalized['status'],
                'remote_run_id' => $normalized['remote_run_id'],
                'result' => json_encode($normalized['result'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
                'received_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } catch (QueryException $exception) {
            if ($this->isUniqueViolation($exception)) {
                $this->events->emit('najm_hoda.integration.n8n.callback_duplicate_receipt', [
                    'request_id' => $normalized['request_id'],
                    'correlation_id' => $normalized['correlation_id'],
                    'scope' => 'integration:n8n',
                    'risk' => 'medium',
                    'workflow' => $normalized['workflow'],
                    'remote_run_id' => $normalized['remote_run_id'],
                    'status' => $normalized['status'],
                ]);

                throw new RuntimeException('n8n callback receipt already exists.', previous: $exception);
            }

            throw $exception;
        }

        $this->events->emit('najm_hoda.integration.n8n.callback_recorded', [
            'request_id' => $normalized['request_id'],
            'correlation_id' => $normalized['correlation_id'],
            'scope' => 'integration:n8n',
            'risk' => $normalized['mode'] === 'read_only' ? 'low' : 'medium',
            'workflow' => $normalized['workflow'],
            'mode' => $normalized['mode'],
            'status' => $normalized['status'],
            'remote_run_id' => $normalized['remote_run_id'],
            'result_sha256' => hash('sha256', json_encode($normalized['result'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)),
        ]);

        return [
            'accepted' => true,
            'request_id' => $normalized['request_id'],
            'correlation_id' => $normalized['correlation_id'],
            'status' => $normalized['status'],
        ];
    }

    protected function assertHttpIngressAllowed(): void
    {
        if (!config('najm-hoda-n8n.callback_http_enabled', false)) {
            throw new RuntimeException('n8n callback HTTP ingress is disabled.');
        }

        if (!$this->controls->callbackIngressEnabled()) {
            throw new RuntimeException('n8n callback HTTP ingress is paused by runtime control.');
        }

        if (!config('najm-hoda-n8n.callback_require_persistent_cache', true)) {
            return;
        }

        $driver = (string) config('cache.default');
        $allowed = config('najm-hoda-n8n.callback_persistent_cache_drivers', ['redis', 'memcached', 'database']);
        if (!is_array($allowed) || !in_array($driver, $allowed, true)) {
            throw new RuntimeException('n8n callback HTTP ingress requires a shared persistent cache driver.');
        }
    }

    protected function isUniqueViolation(QueryException $exception): bool
    {
        $sqlState = (string) ($exception->errorInfo[0] ?? '');
        $driverCode = (string) ($exception->errorInfo[1] ?? '');

        return $sqlState === '23000' || in_array($driverCode, ['1062', '19'], true);
    }
}
