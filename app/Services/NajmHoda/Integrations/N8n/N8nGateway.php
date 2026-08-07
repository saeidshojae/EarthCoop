<?php

namespace App\Services\NajmHoda\Integrations\N8n;

use App\Services\NajmHoda\Runtime\RuntimeEventBus;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use InvalidArgumentException;
use RuntimeException;

class N8nGateway
{
    public function __construct(
        protected RuntimeEventBus $events,
        protected ?N8nRuntimeControlService $controls = null,
    ) {
    }

    /** @return array<string, mixed> */
    public function health(): array
    {
        $this->assertConfigured(false);

        $requestId = (string) Str::uuid();
        $started = microtime(true);

        try {
            $response = $this->request($requestId, 'health')->get($this->url(config('najm-hoda-n8n.health_path')));
            $healthy = $response->successful();

            $this->events->emit('najm_hoda.integration.n8n.health_checked', [
                'request_id' => $requestId,
                'correlation_id' => $requestId,
                'scope' => 'ops',
                'risk' => 'low',
                'healthy' => $healthy,
                'status_code' => $response->status(),
                'duration_ms' => (int) round((microtime(true) - $started) * 1000),
            ]);

            return [
                'healthy' => $healthy,
                'status_code' => $response->status(),
                'request_id' => $requestId,
            ];
        } catch (ConnectionException) {
            $this->events->emit('najm_hoda.integration.n8n.health_failed', [
                'request_id' => $requestId,
                'correlation_id' => $requestId,
                'scope' => 'ops',
                'risk' => 'low',
                'reason' => 'connection_error',
            ]);

            return ['healthy' => false, 'status_code' => null, 'request_id' => $requestId];
        }
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function dispatch(string $workflow, array $payload, ?string $actorId = null, ?string $correlationId = null): array
    {
        $this->assertConfigured(true);

        $allowedWorkflows = config('najm-hoda-n8n.allowed_workflows', []);
        $mode = is_array($allowedWorkflows) ? ($allowedWorkflows[$workflow] ?? null) : null;
        if (!in_array($mode, ['read_only', 'propose_only'], true)) {
            throw new InvalidArgumentException('n8n workflow is not allow-listed for milestone 1.');
        }

        $requestId = (string) Str::uuid();
        $correlationId = $correlationId ?: $requestId;
        $envelope = [
            'version' => 1,
            'request_id' => $requestId,
            'correlation_id' => $correlationId,
            'workflow' => $workflow,
            'mode' => $mode,
            'actor_id' => $actorId ?: 'system',
            'sent_at' => now()->toIso8601String(),
            'payload' => $payload,
        ];

        $json = json_encode($envelope, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        if (strlen($json) > max(1024, (int) config('najm-hoda-n8n.max_payload_bytes', 32768))) {
            throw new InvalidArgumentException('n8n payload exceeds configured size limit.');
        }

        $this->events->emit('najm_hoda.integration.n8n.dispatch_started', [
            'request_id' => $requestId,
            'correlation_id' => $correlationId,
            'actor_id' => $actorId ?: 'system',
            'scope' => 'integration:n8n',
            'risk' => $mode === 'read_only' ? 'low' : 'medium',
            'workflow' => $workflow,
            'mode' => $mode,
        ]);

        try {
            $response = $this->request($requestId, 'dispatch', $json)
                ->withBody($json, 'application/json')
                ->post($this->url(config('najm-hoda-n8n.dispatch_path')));

            if (!$response->successful()) {
                $this->events->emit('najm_hoda.integration.n8n.dispatch_failed', [
                    'request_id' => $requestId,
                    'correlation_id' => $correlationId,
                    'actor_id' => $actorId ?: 'system',
                    'scope' => 'integration:n8n',
                    'risk' => 'medium',
                    'workflow' => $workflow,
                    'status_code' => $response->status(),
                ]);

                throw new RuntimeException('n8n rejected the workflow request.');
            }

            $body = $response->json();
            $this->events->emit('najm_hoda.integration.n8n.dispatch_accepted', [
                'request_id' => $requestId,
                'correlation_id' => $correlationId,
                'actor_id' => $actorId ?: 'system',
                'scope' => 'integration:n8n',
                'risk' => $mode === 'read_only' ? 'low' : 'medium',
                'workflow' => $workflow,
                'status_code' => $response->status(),
                'remote_run_id' => is_array($body) ? ($body['run_id'] ?? null) : null,
            ]);

            return [
                'accepted' => true,
                'request_id' => $requestId,
                'correlation_id' => $correlationId,
                'remote_run_id' => is_array($body) ? ($body['run_id'] ?? null) : null,
                'response' => is_array($body) ? $body : [],
            ];
        } catch (ConnectionException $exception) {
            $this->events->emit('najm_hoda.integration.n8n.dispatch_failed', [
                'request_id' => $requestId,
                'correlation_id' => $correlationId,
                'actor_id' => $actorId ?: 'system',
                'scope' => 'integration:n8n',
                'risk' => 'medium',
                'workflow' => $workflow,
                'reason' => 'connection_error',
            ]);

            throw new RuntimeException('n8n is unavailable.', previous: $exception);
        }
    }

    protected function request(string $requestId, string $purpose, string $body = ''): PendingRequest
    {
        $timestamp = (string) time();
        $secret = (string) config('najm-hoda-n8n.shared_secret');
        $signaturePayload = implode('.', [$timestamp, $requestId, $purpose, hash('sha256', $body)]);
        $signature = hash_hmac('sha256', $signaturePayload, $secret);

        return Http::timeout(max(1, (int) config('najm-hoda-n8n.timeout_seconds', 8)))
            ->acceptJson()
            ->withHeaders([
                'X-NajmHoda-Timestamp' => $timestamp,
                'X-NajmHoda-Request-Id' => $requestId,
                'X-NajmHoda-Purpose' => $purpose,
                'X-NajmHoda-Signature' => 'sha256=' . $signature,
            ]);
    }

    protected function assertConfigured(bool $requireOutboundEnabled): void
    {
        if (!config('najm-hoda-n8n.enabled', false)) {
            throw new RuntimeException('n8n integration is disabled.');
        }

        if ($requireOutboundEnabled && !$this->runtimeControls()->outboundEnabled()) {
            throw new RuntimeException('n8n outbound integration is paused by runtime control.');
        }

        if (trim((string) config('najm-hoda-n8n.base_url')) === '') {
            throw new RuntimeException('n8n base URL is not configured.');
        }

        if (trim((string) config('najm-hoda-n8n.shared_secret')) === '') {
            throw new RuntimeException('n8n shared secret is not configured.');
        }
    }

    protected function runtimeControls(): N8nRuntimeControlService
    {
        return $this->controls ??= app(N8nRuntimeControlService::class);
    }

    protected function url(?string $path): string
    {
        return rtrim((string) config('najm-hoda-n8n.base_url'), '/') . '/' . ltrim((string) $path, '/');
    }
}
