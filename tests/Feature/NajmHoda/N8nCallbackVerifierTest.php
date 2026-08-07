<?php

namespace Tests\Feature\NajmHoda;

use App\Services\NajmHoda\Integrations\N8n\N8nCallbackVerifier;
use App\Services\NajmHoda\Runtime\InMemoryRuntimeEventBus;
use Illuminate\Support\Facades\Cache;
use InvalidArgumentException;
use RuntimeException;
use Tests\TestCase;

class N8nCallbackVerifierTest extends TestCase
{
    private InMemoryRuntimeEventBus $events;
    private N8nCallbackVerifier $verifier;
    private string $secret = 'callback-test-secret-never-log';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'cache.default' => 'array',
            'najm-hoda-n8n.enabled' => true,
            'najm-hoda-n8n.shared_secret' => $this->secret,
            'najm-hoda-n8n.max_payload_bytes' => 4096,
            'najm-hoda-n8n.callback_max_clock_skew_seconds' => 300,
            'najm-hoda-n8n.callback_replay_ttl_seconds' => 900,
            'najm-hoda-n8n.allowed_workflows' => [
                'ops.health.read' => 'read_only',
                'support.triage.propose' => 'propose_only',
            ],
        ]);

        Cache::flush();
        $this->events = new InMemoryRuntimeEventBus();
        $this->verifier = new N8nCallbackVerifier($this->events);
    }

    public function test_valid_signed_callback_is_normalized_and_audited_without_result_leakage(): void
    {
        [$body, $headers] = $this->signedCallback([
            'version' => 1,
            'workflow' => 'support.triage.propose',
            'mode' => 'propose_only',
            'status' => 'completed',
            'correlation_id' => 'corr-123',
            'run_id' => 'run-123',
            'result' => ['summary' => 'proposed response', 'private' => 'do-not-audit'],
        ], 'req-123');

        $result = $this->verifier->verify($body, $headers);

        $this->assertSame('support.triage.propose', $result['workflow']);
        $this->assertSame('propose_only', $result['mode']);
        $this->assertSame('completed', $result['status']);
        $this->assertSame('corr-123', $result['correlation_id']);
        $this->assertSame('run-123', $result['remote_run_id']);
        $this->assertSame('proposed response', $result['result']['summary']);

        $events = $this->events->recent('najm_hoda.integration.n8n.callback_verified', 1);
        $this->assertCount(1, $events);
        $this->assertSame('support.triage.propose', $events[0]['payload']['workflow']);
        $auditJson = json_encode($this->events->recent());
        $this->assertStringNotContainsString($this->secret, $auditJson);
        $this->assertStringNotContainsString('do-not-audit', $auditJson);
    }

    public function test_invalid_signature_is_rejected(): void
    {
        [$body, $headers] = $this->signedCallback($this->basePayload(), 'req-bad-signature');
        $headers['x-najmhoda-signature'] = 'sha256=' . str_repeat('0', 64);

        $this->expectException(InvalidArgumentException::class);
        try {
            $this->verifier->verify($body, $headers);
        } finally {
            $this->assertCount(0, $this->events->recent('najm_hoda.integration.n8n.callback_verified', 1));
        }
    }

    public function test_stale_timestamp_is_rejected(): void
    {
        $timestamp = time() - 1000;
        [$body, $headers] = $this->signedCallback($this->basePayload(), 'req-stale', $timestamp);

        $this->expectException(InvalidArgumentException::class);
        $this->verifier->verify($body, $headers);
    }

    public function test_replayed_callback_is_rejected_and_audited(): void
    {
        [$body, $headers] = $this->signedCallback($this->basePayload(), 'req-replay');

        $this->verifier->verify($body, $headers);

        try {
            $this->verifier->verify($body, $headers);
            $this->fail('Replay should have been rejected.');
        } catch (InvalidArgumentException $exception) {
            $this->assertStringContainsString('replay', strtolower($exception->getMessage()));
        }

        $this->assertCount(1, $this->events->recent('najm_hoda.integration.n8n.callback_replayed', 1));
    }

    public function test_unknown_or_apply_workflow_callback_is_rejected(): void
    {
        config(['najm-hoda-n8n.allowed_workflows' => ['danger.apply' => 'apply']]);
        [$body, $headers] = $this->signedCallback([
            'version' => 1,
            'workflow' => 'danger.apply',
            'mode' => 'apply',
            'status' => 'completed',
            'correlation_id' => 'corr-danger',
            'run_id' => 'run-danger',
            'result' => [],
        ], 'req-danger');

        $this->expectException(InvalidArgumentException::class);
        $this->verifier->verify($body, $headers);
    }

    public function test_oversized_callback_is_rejected_before_signature_processing(): void
    {
        config(['najm-hoda-n8n.max_payload_bytes' => 1024]);
        $body = json_encode(['blob' => str_repeat('x', 3000)], JSON_THROW_ON_ERROR);

        $this->expectException(InvalidArgumentException::class);
        $this->verifier->verify($body, []);
    }

    public function test_integration_is_fail_closed_when_disabled(): void
    {
        config(['najm-hoda-n8n.enabled' => false]);
        [$body, $headers] = $this->signedCallback($this->basePayload(), 'req-disabled');

        $this->expectException(RuntimeException::class);
        $this->verifier->verify($body, $headers);
    }

    /** @return array<string, mixed> */
    private function basePayload(): array
    {
        return [
            'version' => 1,
            'workflow' => 'ops.health.read',
            'mode' => 'read_only',
            'status' => 'completed',
            'correlation_id' => 'corr-health',
            'run_id' => 'run-health',
            'result' => ['ok' => true],
        ];
    }

    /**
     * @param array<string, mixed> $payload
     * @return array{0:string,1:array<string,string>}
     */
    private function signedCallback(array $payload, string $requestId, ?int $timestamp = null): array
    {
        $body = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        $timestamp ??= time();
        $signaturePayload = implode('.', [
            (string) $timestamp,
            $requestId,
            'callback',
            hash('sha256', $body),
        ]);
        $signature = hash_hmac('sha256', $signaturePayload, $this->secret);

        return [$body, [
            'x-najmhoda-timestamp' => (string) $timestamp,
            'x-najmhoda-request-id' => $requestId,
            'x-najmhoda-purpose' => 'callback',
            'x-najmhoda-signature' => 'sha256=' . $signature,
        ]];
    }
}
