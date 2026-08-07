<?php

namespace Tests\Feature\NajmHoda;

use App\Services\NajmHoda\Integrations\N8n\N8nGateway;
use App\Services\NajmHoda\Runtime\InMemoryRuntimeEventBus;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;
use RuntimeException;
use Tests\TestCase;

class N8nGatewayTest extends TestCase
{
    private InMemoryRuntimeEventBus $events;
    private N8nGateway $gateway;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'najm-hoda-n8n.enabled' => true,
            'najm-hoda-n8n.base_url' => 'https://n8n.internal.test',
            'najm-hoda-n8n.health_path' => '/healthz',
            'najm-hoda-n8n.dispatch_path' => '/webhook/najm-hoda',
            'najm-hoda-n8n.shared_secret' => 'test-secret-never-log',
            'najm-hoda-n8n.max_payload_bytes' => 4096,
            'najm-hoda-n8n.allowed_workflows' => [
                'ops.health.read' => 'read_only',
                'support.triage.propose' => 'propose_only',
            ],
        ]);

        $this->events = new InMemoryRuntimeEventBus();
        $this->gateway = new N8nGateway($this->events);
    }

    public function test_integration_is_fail_closed_when_disabled(): void
    {
        config(['najm-hoda-n8n.enabled' => false]);

        $this->expectException(RuntimeException::class);
        $this->gateway->health();
    }

    public function test_unknown_or_apply_workflow_cannot_be_dispatched(): void
    {
        config(['najm-hoda-n8n.allowed_workflows.danger.apply' => 'apply']);

        $this->expectException(InvalidArgumentException::class);
        $this->gateway->dispatch('danger.apply', ['command' => 'nope']);
    }

    public function test_signed_propose_dispatch_preserves_correlation_and_emits_audit_events(): void
    {
        Http::fake([
            'https://n8n.internal.test/webhook/najm-hoda' => Http::response(['run_id' => 'run-123'], 202),
        ]);

        $result = $this->gateway->dispatch(
            'support.triage.propose',
            ['ticket_id' => 42],
            'user:7',
            'corr-abc'
        );

        $this->assertTrue($result['accepted']);
        $this->assertSame('corr-abc', $result['correlation_id']);
        $this->assertSame('run-123', $result['remote_run_id']);

        Http::assertSent(function (Request $request): bool {
            $body = $request->data();
            $signature = $request->header('X-NajmHoda-Signature')[0] ?? '';
            $timestamp = $request->header('X-NajmHoda-Timestamp')[0] ?? '';
            $requestId = $request->header('X-NajmHoda-Request-Id')[0] ?? '';

            return $request->url() === 'https://n8n.internal.test/webhook/najm-hoda'
                && str_starts_with($signature, 'sha256=')
                && ctype_digit($timestamp)
                && $requestId !== ''
                && ($body['workflow'] ?? null) === 'support.triage.propose'
                && ($body['mode'] ?? null) === 'propose_only'
                && ($body['correlation_id'] ?? null) === 'corr-abc'
                && ($body['payload']['ticket_id'] ?? null) === 42;
        });

        $started = $this->events->recent('najm_hoda.integration.n8n.dispatch_started', 1);
        $accepted = $this->events->recent('najm_hoda.integration.n8n.dispatch_accepted', 1);

        $this->assertCount(1, $started);
        $this->assertCount(1, $accepted);
        $this->assertSame('support.triage.propose', $accepted[0]['payload']['workflow']);
        $this->assertSame('corr-abc', $accepted[0]['payload']['correlation_id']);
        $this->assertArrayNotHasKey('payload', $accepted[0]['payload']);
        $this->assertStringNotContainsString('test-secret-never-log', json_encode($this->events->recent()));
    }

    public function test_health_check_is_signed_and_audited(): void
    {
        Http::fake([
            'https://n8n.internal.test/healthz' => Http::response(['ok' => true], 200),
        ]);

        $result = $this->gateway->health();

        $this->assertTrue($result['healthy']);
        $this->assertSame(200, $result['status_code']);
        $this->assertCount(1, $this->events->recent('najm_hoda.integration.n8n.health_checked', 1));

        Http::assertSent(fn (Request $request): bool =>
            $request->url() === 'https://n8n.internal.test/healthz'
            && ($request->header('X-NajmHoda-Purpose')[0] ?? '') === 'health'
            && str_starts_with(($request->header('X-NajmHoda-Signature')[0] ?? ''), 'sha256=')
        );
    }

    public function test_oversized_payload_is_rejected_before_network_dispatch(): void
    {
        config(['najm-hoda-n8n.max_payload_bytes' => 1024]);
        Http::fake();

        try {
            $this->gateway->dispatch('support.triage.propose', [
                'body' => str_repeat('x', 3000),
            ]);
            $this->fail('Oversized payload should have been rejected.');
        } catch (InvalidArgumentException) {
            Http::assertNothingSent();
        }
    }
}
