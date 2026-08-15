<?php

namespace Tests\Feature\NajmHoda;

use App\Services\NajmHoda\Integrations\N8n\N8nGateway;
use App\Services\NajmHoda\Integrations\N8n\N8nReadinessService;
use App\Services\NajmHoda\Integrations\N8n\N8nRuntimeControlService;
use App\Services\NajmHoda\Runtime\InMemoryRuntimeEventBus;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\TestCase;

class N8nAdminManagementTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        DB::table('najm_hoda_n8n_runtime_controls')->delete();
        DB::table('najm_hoda_n8n_callbacks')->delete();

        config([
            'cache.default' => 'redis',
            'najm-hoda-n8n.enabled' => true,
            'najm-hoda-n8n.base_url' => 'https://n8n-staging.example.test',
            'najm-hoda-n8n.health_path' => '/healthz',
            'najm-hoda-n8n.dispatch_path' => '/webhook/najm-hoda',
            'najm-hoda-n8n.shared_secret' => str_repeat('s', 48),
            'najm-hoda-n8n.callback_http_enabled' => true,
            'najm-hoda-n8n.callback_require_persistent_cache' => true,
            'najm-hoda-n8n.allowed_workflows' => [
                'ops.health.read' => 'read_only',
                'support.triage.propose' => 'propose_only',
            ],
        ]);
    }

    public function test_runtime_controls_default_open_but_can_be_paused_durably(): void
    {
        $controls = app(N8nRuntimeControlService::class);
        $this->assertTrue($controls->outboundEnabled());
        $this->assertTrue($controls->callbackIngressEnabled());

        $state = $controls->update(false, false, null, 'gameday pause');

        $this->assertFalse($state['outbound_enabled']);
        $this->assertFalse($state['callback_ingress_enabled']);
        $this->assertDatabaseCount('najm_hoda_n8n_runtime_controls', 1);
    }

    public function test_paused_outbound_blocks_network_dispatch(): void
    {
        $events = new InMemoryRuntimeEventBus();
        $controls = new N8nRuntimeControlService($events);
        $controls->update(false, true, null, 'pause outbound');
        Http::fake();

        $gateway = new N8nGateway($events, $controls);

        try {
            $gateway->dispatch('ops.health.read', []);
            $this->fail('Runtime pause must block outbound dispatch.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('paused', strtolower($exception->getMessage()));
            Http::assertNothingSent();
        }
    }

    public function test_single_allow_listed_workflow_can_be_paused_without_disabling_health_diagnostics(): void
    {
        $events = new InMemoryRuntimeEventBus();
        $controls = new N8nRuntimeControlService($events);
        $controls->update(true, true, null, 'pause support only', ['support.triage.propose']);
        Http::fake([
            'https://n8n-staging.example.test/healthz' => Http::response(['ok' => true], 200),
        ]);
        $gateway = new N8nGateway($events, $controls);

        try {
            $gateway->dispatch('support.triage.propose', ['ticket_id' => 10]);
            $this->fail('Disabled workflow must not dispatch.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('workflow', strtolower($exception->getMessage()));
        }

        $health = $gateway->health();
        $this->assertTrue($health['healthy']);
        Http::assertSent(fn (Request $request): bool => $request->url() === 'https://n8n-staging.example.test/healthz');
    }

    public function test_secret_rotation_verification_stores_metadata_but_not_secret(): void
    {
        $events = new InMemoryRuntimeEventBus();
        $controls = new N8nRuntimeControlService($events);

        $state = $controls->markSecretRotationVerified(null, 'verified on staging');

        $this->assertNotNull($state['secret_rotation_verified_at']);
        $row = DB::table('najm_hoda_n8n_runtime_controls')->latest('id')->first();
        $encoded = json_encode($row, JSON_THROW_ON_ERROR);
        $this->assertStringNotContainsString(str_repeat('s', 48), $encoded);
        $this->assertCount(1, $events->recent('najm_hoda.integration.n8n.secret_rotation_verified', 1));
    }

    public function test_readiness_report_never_returns_raw_secret(): void
    {
        $report = app(N8nReadinessService::class)->report();
        $encoded = json_encode($report, JSON_THROW_ON_ERROR);

        $this->assertSame('ready', $report['status']);
        $this->assertTrue($report['secret_configured']);
        $this->assertTrue($report['secret_length_ok']);
        $this->assertStringNotContainsString(str_repeat('s', 48), $encoded);
    }

    public function test_admin_n8n_routes_are_registered(): void
    {
        $this->assertSame(
            'http://localhost/admin/najm-hoda/n8n',
            route('admin.najm-hoda.n8n.index')
        );
        $this->assertStringContainsString('/admin/najm-hoda/n8n/health', route('admin.najm-hoda.n8n.health'));
        $this->assertStringContainsString('/admin/najm-hoda/n8n/controls', route('admin.najm-hoda.n8n.controls.update'));
        $this->assertStringContainsString('/admin/najm-hoda/n8n/secret-rotation/verify', route('admin.najm-hoda.n8n.secret-rotation.verify'));
    }
}
