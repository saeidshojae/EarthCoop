<?php

namespace Tests\Feature\NajmHoda;

use App\Services\NajmHoda\Integrations\N8n\N8nGateway;
use App\Services\NajmHoda\Integrations\N8n\N8nReadinessService;
use App\Services\NajmHoda\Integrations\N8n\N8nRuntimeControlService;
use App\Services\NajmHoda\Runtime\InMemoryRuntimeEventBus;
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
    }
}
