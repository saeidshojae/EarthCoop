<?php

namespace Tests\Feature\NajmHoda;

use App\Services\NajmHoda\Integrations\N8n\N8nGateway;
use App\Services\NajmHoda\Runtime\InMemoryRuntimeEventBus;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\TestCase;

class N8nIntegrationGameDayTest extends TestCase
{
    private string $secret = 'gameday-secret-never-log';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'cache.default' => 'array',
            'najm-hoda.runtime.event_bus.driver' => 'in_memory',
            'najm-hoda-n8n.enabled' => true,
            'najm-hoda-n8n.base_url' => 'https://n8n.internal.test',
            'najm-hoda-n8n.dispatch_path' => '/webhook/najm-hoda',
            'najm-hoda-n8n.health_path' => '/healthz',
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

    public function test_gameday_outbound_n8n_outage_fails_closed_and_is_audited(): void
    {
        Http::fake([
            'https://n8n.internal.test/webhook/najm-hoda' => Http::failedConnection(),
        ]);

        $events = new InMemoryRuntimeEventBus();
        $gateway = new N8nGateway($events);

        try {
            $gateway->dispatch('support.triage.propose', ['ticket_id' => 42], 'user:7', 'corr-gameday-outage');
            $this->fail('n8n outage must fail closed.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('unavailable', strtolower($exception->getMessage()));
        }

        $failed = $events->recent('najm_hoda.integration.n8n.dispatch_failed', 1);
        $this->assertCount(1, $failed);
        $this->assertSame('connection_error', $failed[0]['payload']['reason'] ?? null);
        $this->assertSame('corr-gameday-outage', $failed[0]['payload']['correlation_id'] ?? null);
    }

    public function test_gameday_stale_callback_is_rejected_without_receipt(): void
    {
        [$body, $server] = $this->signedRequest($this->basePayload('completed', 'run-stale'), 'req-stale', time() - 1000);

        $this->call('POST', '/api/internal/najm-hoda/n8n/callback', [], [], [], $server, $body)
            ->assertStatus(422)
            ->assertJson(['accepted' => false, 'error' => 'invalid_callback']);

        $this->assertDatabaseCount('najm_hoda_n8n_callbacks', 0);
    }

    public function test_gameday_exact_replay_is_rejected_and_only_one_receipt_exists(): void
    {
        [$body, $server] = $this->signedRequest($this->basePayload('completed', 'run-replay'), 'req-replay');

        $this->call('POST', '/api/internal/najm-hoda/n8n/callback', [], [], [], $server, $body)
            ->assertStatus(202);

        $this->call('POST', '/api/internal/najm-hoda/n8n/callback', [], [], [], $server, $body)
            ->assertStatus(422)
            ->assertJson(['accepted' => false, 'error' => 'invalid_callback']);

        $this->assertDatabaseCount('najm_hoda_n8n_callbacks', 1);
    }

    public function test_gameday_duplicate_request_id_with_new_status_hits_database_idempotency_guard(): void
    {
        [$body1, $server1] = $this->signedRequest($this->basePayload('progress', 'run-duplicate'), 'req-duplicate');
        $this->call('POST', '/api/internal/najm-hoda/n8n/callback', [], [], [], $server1, $body1)
            ->assertStatus(202);

        [$body2, $server2] = $this->signedRequest($this->basePayload('completed', 'run-duplicate'), 'req-duplicate');
        $this->call('POST', '/api/internal/najm-hoda/n8n/callback', [], [], [], $server2, $body2)
            ->assertStatus(503)
            ->assertJson(['accepted' => false, 'error' => 'callback_unavailable']);

        $this->assertDatabaseCount('najm_hoda_n8n_callbacks', 1);
        $receipt = DB::table('najm_hoda_n8n_callbacks')->where('request_id', 'req-duplicate')->first();
        $this->assertSame('progress', $receipt?->status);
    }

    public function test_gameday_callback_flood_is_rate_limited(): void
    {
        for ($i = 1; $i <= 30; $i++) {
            [$body, $server] = $this->signedRequest(
                $this->basePayload('progress', 'run-flood-' . $i),
                'req-flood-' . $i
            );
            $this->call('POST', '/api/internal/najm-hoda/n8n/callback', [], [], [], $server, $body)
                ->assertStatus(202);
        }

        [$body, $server] = $this->signedRequest($this->basePayload('progress', 'run-flood-31'), 'req-flood-31');
        $this->call('POST', '/api/internal/najm-hoda/n8n/callback', [], [], [], $server, $body)
            ->assertStatus(429);

        $this->assertDatabaseCount('najm_hoda_n8n_callbacks', 30);
    }

    /** @return array<string, mixed> */
    private function basePayload(string $status, string $runId): array
    {
        return [
            'version' => 1,
            'workflow' => 'ops.health.read',
            'mode' => 'read_only',
            'status' => $status,
            'correlation_id' => 'corr-' . $runId,
            'run_id' => $runId,
            'result' => ['ok' => true],
        ];
    }

    /**
     * @param array<string, mixed> $payload
     * @return array{0:string,1:array<string,string>}
     */
    private function signedRequest(array $payload, string $requestId, ?int $timestamp = null): array
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
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
            'REMOTE_ADDR' => '203.0.113.10',
            'HTTP_X_NAJMHODA_TIMESTAMP' => (string) $timestamp,
            'HTTP_X_NAJMHODA_REQUEST_ID' => $requestId,
            'HTTP_X_NAJMHODA_PURPOSE' => 'callback',
            'HTTP_X_NAJMHODA_SIGNATURE' => 'sha256=' . $signature,
        ]];
    }
}
