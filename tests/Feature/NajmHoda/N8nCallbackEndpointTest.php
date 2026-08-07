<?php

namespace Tests\Feature\NajmHoda;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class N8nCallbackEndpointTest extends TestCase
{
    private string $secret = 'endpoint-test-secret-never-log';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'cache.default' => 'array',
            'najm-hoda.runtime.event_bus.driver' => 'in_memory',
            'najm-hoda-n8n.enabled' => true,
            'najm-hoda-n8n.shared_secret' => $this->secret,
            'najm-hoda-n8n.callback_http_enabled' => true,
            'najm-hoda-n8n.callback_require_persistent_cache' => false,
            'najm-hoda-n8n.callback_max_clock_skew_seconds' => 300,
            'najm-hoda-n8n.callback_replay_ttl_seconds' => 900,
            'najm-hoda-n8n.max_payload_bytes' => 4096,
            'najm-hoda-n8n.allowed_workflows' => [
                'ops.health.read' => 'read_only',
                'support.triage.propose' => 'propose_only',
            ],
        ]);

        Cache::flush();
        DB::table('najm_hoda_n8n_callbacks')->delete();
    }

    public function test_valid_signed_callback_is_accepted_and_persisted_without_execution(): void
    {
        $payload = [
            'version' => 1,
            'workflow' => 'support.triage.propose',
            'mode' => 'propose_only',
            'status' => 'completed',
            'correlation_id' => 'corr-endpoint-1',
            'run_id' => 'run-endpoint-1',
            'result' => ['summary' => 'proposal only', 'execute' => ['capability' => 'forbidden']],
        ];
        [$body, $server] = $this->signedRequest($payload, 'req-endpoint-1');

        $response = $this->call(
            'POST',
            '/api/internal/najm-hoda/n8n/callback',
            [],
            [],
            [],
            $server,
            $body,
        );

        $response->assertStatus(202)
            ->assertJson([
                'accepted' => true,
                'request_id' => 'req-endpoint-1',
                'correlation_id' => 'corr-endpoint-1',
                'status' => 'completed',
            ]);

        $receipt = DB::table('najm_hoda_n8n_callbacks')->where('request_id', 'req-endpoint-1')->first();
        $this->assertNotNull($receipt);
        $this->assertSame('support.triage.propose', $receipt->workflow);
        $this->assertSame('completed', $receipt->status);
        $this->assertStringContainsString('proposal only', (string) $receipt->result);
        $this->assertDatabaseCount('najm_hoda_n8n_callbacks', 1);
    }

    public function test_invalid_signature_is_rejected_without_receipt(): void
    {
        [$body, $server] = $this->signedRequest($this->basePayload(), 'req-endpoint-bad');
        $server['HTTP_X_NAJMHODA_SIGNATURE'] = 'sha256=' . str_repeat('0', 64);

        $this->call('POST', '/api/internal/najm-hoda/n8n/callback', [], [], [], $server, $body)
            ->assertStatus(422)
            ->assertJson(['accepted' => false, 'error' => 'invalid_callback']);

        $this->assertDatabaseCount('najm_hoda_n8n_callbacks', 0);
    }

    public function test_http_ingress_is_fail_closed_when_disabled(): void
    {
        config(['najm-hoda-n8n.callback_http_enabled' => false]);
        [$body, $server] = $this->signedRequest($this->basePayload(), 'req-endpoint-disabled');

        $this->call('POST', '/api/internal/najm-hoda/n8n/callback', [], [], [], $server, $body)
            ->assertStatus(503)
            ->assertJson(['accepted' => false, 'error' => 'callback_unavailable']);

        $this->assertDatabaseCount('najm_hoda_n8n_callbacks', 0);
    }

    public function test_http_ingress_requires_shared_persistent_cache_when_guard_enabled(): void
    {
        config([
            'najm-hoda-n8n.callback_require_persistent_cache' => true,
            'cache.default' => 'array',
        ]);
        [$body, $server] = $this->signedRequest($this->basePayload(), 'req-endpoint-cache');

        $this->call('POST', '/api/internal/najm-hoda/n8n/callback', [], [], [], $server, $body)
            ->assertStatus(503)
            ->assertJson(['accepted' => false, 'error' => 'callback_unavailable']);

        $this->assertDatabaseCount('najm_hoda_n8n_callbacks', 0);
    }

    /** @return array<string, mixed> */
    private function basePayload(): array
    {
        return [
            'version' => 1,
            'workflow' => 'ops.health.read',
            'mode' => 'read_only',
            'status' => 'completed',
            'correlation_id' => 'corr-endpoint-health',
            'run_id' => 'run-endpoint-health',
            'result' => ['ok' => true],
        ];
    }

    /**
     * @param array<string, mixed> $payload
     * @return array{0:string,1:array<string,string>}
     */
    private function signedRequest(array $payload, string $requestId): array
    {
        $body = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        $timestamp = time();
        $signaturePayload = implode('.', [
            (string) $timestamp,
            $requestId,
            'callback',
            hash('sha256', $body),
        ]);
        $signature = hash_hmac('sha256', $signaturePayload, $this->secret);

        return [$body, [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
            'HTTP_X_NAJMHODA_TIMESTAMP' => (string) $timestamp,
            'HTTP_X_NAJMHODA_REQUEST_ID' => $requestId,
            'HTTP_X_NAJMHODA_PURPOSE' => 'callback',
            'HTTP_X_NAJMHODA_SIGNATURE' => 'sha256=' . $signature,
        ]];
    }
}
