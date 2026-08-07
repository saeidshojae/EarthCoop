<?php

namespace App\Services\NajmHoda\Integrations\N8n;

use App\Services\NajmHoda\Runtime\RuntimeEventBus;
use Illuminate\Support\Facades\Cache;
use InvalidArgumentException;
use RuntimeException;

class N8nCallbackVerifier
{
    public function __construct(
        protected RuntimeEventBus $events,
        protected N8nWorkflowContractValidator $contracts,
    ) {
    }

    /**
     * Validate and normalize a signed callback from n8n.
     *
     * This class deliberately does not execute any capability. It only verifies
     * authenticity/freshness/idempotency, validates the workflow result contract,
     * and records a normalized audit event.
     *
     * @param array<string, string|null> $headers
     * @return array<string, mixed>
     */
    public function verify(string $rawBody, array $headers): array
    {
        $this->assertConfigured();

        if (strlen($rawBody) > max(1024, (int) config('najm-hoda-n8n.max_payload_bytes', 32768))) {
            throw new InvalidArgumentException('n8n callback payload exceeds configured size limit.');
        }

        $timestamp = trim((string) ($headers['x-najmhoda-timestamp'] ?? ''));
        $requestId = trim((string) ($headers['x-najmhoda-request-id'] ?? ''));
        $purpose = trim((string) ($headers['x-najmhoda-purpose'] ?? ''));
        $signature = trim((string) ($headers['x-najmhoda-signature'] ?? ''));

        if ($timestamp === '' || !ctype_digit($timestamp) || $requestId === '' || $purpose !== 'callback' || $signature === '') {
            throw new InvalidArgumentException('n8n callback headers are incomplete.');
        }

        $skew = max(30, (int) config('najm-hoda-n8n.callback_max_clock_skew_seconds', 300));
        if (abs(time() - (int) $timestamp) > $skew) {
            throw new InvalidArgumentException('n8n callback timestamp is stale.');
        }

        $secret = (string) config('najm-hoda-n8n.shared_secret');
        $signaturePayload = implode('.', [$timestamp, $requestId, 'callback', hash('sha256', $rawBody)]);
        $expected = 'sha256=' . hash_hmac('sha256', $signaturePayload, $secret);
        if (!hash_equals($expected, $signature)) {
            throw new InvalidArgumentException('n8n callback signature is invalid.');
        }

        $decoded = json_decode($rawBody, true, flags: JSON_THROW_ON_ERROR);
        if (!is_array($decoded)) {
            throw new InvalidArgumentException('n8n callback body must be an object.');
        }

        $workflow = trim((string) ($decoded['workflow'] ?? ''));
        $mode = trim((string) ($decoded['mode'] ?? ''));
        $status = trim((string) ($decoded['status'] ?? ''));
        $correlationId = trim((string) ($decoded['correlation_id'] ?? ''));
        $remoteRunId = trim((string) ($decoded['run_id'] ?? ''));

        $allowedWorkflows = config('najm-hoda-n8n.allowed_workflows', []);
        $expectedMode = is_array($allowedWorkflows) ? ($allowedWorkflows[$workflow] ?? null) : null;
        if (!in_array($expectedMode, ['read_only', 'propose_only'], true) || $mode !== $expectedMode) {
            throw new InvalidArgumentException('n8n callback workflow or mode is not allowed.');
        }

        if (!in_array($status, ['progress', 'completed', 'failed'], true)) {
            throw new InvalidArgumentException('n8n callback status is invalid.');
        }

        if ($correlationId === '' || $remoteRunId === '') {
            throw new InvalidArgumentException('n8n callback identifiers are incomplete.');
        }

        $rawResult = is_array($decoded['result'] ?? null) ? $decoded['result'] : [];
        $result = $this->contracts->validateResult($workflow, $status, $rawResult);

        $ttl = max($skew, (int) config('najm-hoda-n8n.callback_replay_ttl_seconds', 900));
        $replayKey = 'najm_hoda:n8n:callback:' . hash('sha256', $requestId . '|' . $remoteRunId . '|' . $status);
        if (!Cache::add($replayKey, true, now()->addSeconds($ttl))) {
            $this->events->emit('najm_hoda.integration.n8n.callback_replayed', [
                'request_id' => $requestId,
                'correlation_id' => $correlationId,
                'scope' => 'integration:n8n',
                'risk' => 'medium',
                'workflow' => $workflow,
                'remote_run_id' => $remoteRunId,
                'status' => $status,
            ]);

            throw new InvalidArgumentException('n8n callback replay detected.');
        }

        $normalized = [
            'version' => max(1, (int) ($decoded['version'] ?? 1)),
            'request_id' => $requestId,
            'correlation_id' => $correlationId,
            'workflow' => $workflow,
            'mode' => $mode,
            'status' => $status,
            'remote_run_id' => $remoteRunId,
            'result' => $result,
        ];

        $this->events->emit('najm_hoda.integration.n8n.callback_verified', [
            'request_id' => $requestId,
            'correlation_id' => $correlationId,
            'scope' => 'integration:n8n',
            'risk' => $mode === 'read_only' ? 'low' : 'medium',
            'workflow' => $workflow,
            'mode' => $mode,
            'status' => $status,
            'remote_run_id' => $remoteRunId,
        ]);

        return $normalized;
    }

    protected function assertConfigured(): void
    {
        if (!config('najm-hoda-n8n.enabled', false)) {
            throw new RuntimeException('n8n integration is disabled.');
        }

        if (trim((string) config('najm-hoda-n8n.shared_secret')) === '') {
            throw new RuntimeException('n8n shared secret is not configured.');
        }
    }
}
