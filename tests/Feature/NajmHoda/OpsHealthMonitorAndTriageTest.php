<?php

namespace Tests\Feature\NajmHoda;

use App\Services\NajmHoda\Runtime\InMemoryRuntimeEventBus;
use App\Services\NajmHoda\Runtime\NajmHodaOpsHealthMonitor;
use App\Services\NajmHoda\Runtime\NajmHodaOpsTriageService;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class OpsHealthMonitorAndTriageTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'cache.default' => 'array',
            'najm-hoda.runtime.ops.monitor.window_minutes' => 15,
            'najm-hoda.runtime.ops.monitor.recent_limit' => 400,
            'najm-hoda.runtime.ops.thresholds.warning_error_rate_percent' => 15,
            'najm-hoda.runtime.ops.thresholds.critical_error_rate_percent' => 35,
            'najm-hoda.runtime.ops.thresholds.warning_unresolved_requests' => 4,
            'najm-hoda.runtime.ops.thresholds.critical_unresolved_requests' => 10,
            'najm-hoda.runtime.ops.triage.auto_playbook_enabled' => true,
            'najm-hoda.runtime.ops.triage.degraded_ttl_seconds' => 300,
            'najm-hoda.runtime.ops.triage.entry_rate_multiplier_base' => 1.0,
            'najm-hoda.runtime.ops.triage.entry_rate_multiplier_warning' => 0.8,
            'najm-hoda.runtime.ops.triage.entry_rate_multiplier_critical' => 0.5,
            'najm-hoda.runtime.ops.playbooks.enforce_low_risk_only' => true,
            'najm-hoda.runtime.ops.playbooks.max_actions_per_run' => 5,
            'najm-hoda.runtime.ops.playbooks.default_action_cooldown_seconds' => 0,
            'najm-hoda.runtime.ops.playbooks.action_cooldowns.set_degraded_mode' => 0,
            'najm-hoda.runtime.ops.playbooks.action_cooldowns.clear_degraded_mode' => 0,
            'najm-hoda.runtime.ops.playbooks.action_cooldowns.set_entry_rate_multiplier_base' => 0,
            'najm-hoda.runtime.ops.playbooks.action_cooldowns.set_entry_rate_multiplier_warning' => 0,
            'najm-hoda.runtime.ops.playbooks.action_cooldowns.set_entry_rate_multiplier_critical' => 0,
            'najm-hoda.runtime.ops.playbooks.plan.healthy' => ['clear_degraded_mode', 'set_entry_rate_multiplier_base'],
            'najm-hoda.runtime.ops.playbooks.plan.warning' => ['set_degraded_mode', 'set_entry_rate_multiplier_warning'],
            'najm-hoda.runtime.ops.playbooks.plan.critical' => ['set_degraded_mode', 'set_entry_rate_multiplier_critical'],
            'najm-hoda.runtime.ops.playbooks.catalog.clear_degraded_mode' => ['enabled' => true, 'risk' => 'low'],
            'najm-hoda.runtime.ops.playbooks.catalog.set_degraded_mode' => ['enabled' => true, 'risk' => 'low'],
            'najm-hoda.runtime.ops.playbooks.catalog.set_entry_rate_multiplier_base' => ['enabled' => true, 'risk' => 'low'],
            'najm-hoda.runtime.ops.playbooks.catalog.set_entry_rate_multiplier_warning' => ['enabled' => true, 'risk' => 'low'],
            'najm-hoda.runtime.ops.playbooks.catalog.set_entry_rate_multiplier_critical' => ['enabled' => true, 'risk' => 'low'],
        ]);

        Cache::flush();
    }

    public function test_health_snapshot_is_healthy_when_failures_are_low(): void
    {
        $bus = new InMemoryRuntimeEventBus(200);
        $bus->emit('najm_hoda.request.received', ['request_id' => '1']);
        $bus->emit('najm_hoda.response.ready', ['request_id' => '1']);
        $bus->emit('najm_hoda.request.received', ['request_id' => '2']);
        $bus->emit('najm_hoda.response.ready', ['request_id' => '2']);

        $monitor = new NajmHodaOpsHealthMonitor($bus);
        $snapshot = $monitor->snapshot(15, 200);

        $this->assertSame('healthy', $snapshot['status']);
        $this->assertSame(0.0, $snapshot['metrics']['error_rate_percent']);
        $this->assertSame(0, $snapshot['metrics']['unresolved_requests']);
    }

    public function test_triage_sets_degraded_mode_on_critical_snapshot(): void
    {
        $bus = new InMemoryRuntimeEventBus(300);

        for ($i = 0; $i < 10; $i++) {
            $bus->emit('najm_hoda.request.received', ['request_id' => (string) $i]);
        }

        for ($i = 0; $i < 6; $i++) {
            $bus->emit('najm_hoda.response.failed', ['request_id' => (string) $i]);
        }

        $monitor = new NajmHodaOpsHealthMonitor($bus);
        $triage = new NajmHodaOpsTriageService($bus);

        $snapshot = $monitor->snapshot(15, 300);
        $incidents = $triage->processSnapshot($snapshot, true);

        $this->assertSame('critical', $snapshot['status']);
        $this->assertNotEmpty($incidents);
        $this->assertSame('critical', $incidents[0]['severity']);
        $this->assertNotNull(Cache::get('najm_hoda:ops:degraded_until'));
        $this->assertSame(0.5, (float) Cache::get('najm_hoda:ops:entry_rate_multiplier'));
    }

    public function test_triage_resets_entry_rate_multiplier_when_healthy(): void
    {
        Cache::put('najm_hoda:ops:degraded_until', now()->addMinutes(5)->timestamp, now()->addMinutes(6));
        Cache::put('najm_hoda:ops:entry_rate_multiplier', 0.5, now()->addMinutes(6));

        $bus = new InMemoryRuntimeEventBus(200);
        $bus->emit('najm_hoda.request.received', ['request_id' => '1']);
        $bus->emit('najm_hoda.response.ready', ['request_id' => '1']);

        $monitor = new NajmHodaOpsHealthMonitor($bus);
        $triage = new NajmHodaOpsTriageService($bus);

        $snapshot = $monitor->snapshot(15, 200);
        $triage->processSnapshot($snapshot, true);

        $this->assertNull(Cache::get('najm_hoda:ops:degraded_until'));
        $this->assertSame(1.0, (float) Cache::get('najm_hoda:ops:entry_rate_multiplier'));
    }

    public function test_triage_skips_high_risk_playbook_actions(): void
    {
        config([
            'najm-hoda.runtime.ops.playbooks.plan.warning' => [
                'set_degraded_mode',
                'set_entry_rate_multiplier_warning',
                'high_risk_dummy_action',
            ],
            'najm-hoda.runtime.ops.playbooks.catalog.high_risk_dummy_action' => [
                'enabled' => true,
                'risk' => 'high',
            ],
        ]);

        $bus = new InMemoryRuntimeEventBus(300);
        for ($i = 0; $i < 5; $i++) {
            $bus->emit('najm_hoda.request.received', ['request_id' => (string) $i]);
        }
        for ($i = 1; $i < 5; $i++) {
            $bus->emit('najm_hoda.response.ready', ['request_id' => (string) $i]);
        }
        $bus->emit('najm_hoda.response.failed', ['request_id' => '0']);

        $monitor = new NajmHodaOpsHealthMonitor($bus);
        $triage = new NajmHodaOpsTriageService($bus);

        $snapshot = $monitor->snapshot(15, 300);
        $triage->processSnapshot($snapshot, true);

        $this->assertSame('warning', $snapshot['status']);
        $this->assertNotNull(Cache::get('najm_hoda:ops:degraded_until'));
        $this->assertSame(0.8, (float) Cache::get('najm_hoda:ops:entry_rate_multiplier'));

        $skipped = $bus->recent('najm_hoda.ops.playbook.skipped', 10);
        $this->assertNotEmpty($skipped);
        $this->assertSame('high_risk_dummy_action', data_get($skipped[0], 'payload.action'));
        $this->assertSame('high_risk_blocked', data_get($skipped[0], 'payload.reason'));
    }

    public function test_triage_skips_action_when_playbook_cooldown_is_active_and_emits_telemetry(): void
    {
        config([
            'najm-hoda.runtime.ops.playbooks.action_cooldowns.set_degraded_mode' => 120,
        ]);

        Cache::put('najm_hoda:ops:playbook:cooldown:set_degraded_mode', 1, now()->addMinutes(3));

        $bus = new InMemoryRuntimeEventBus(400);
        for ($i = 0; $i < 5; $i++) {
            $bus->emit('najm_hoda.request.received', ['request_id' => (string) $i]);
        }
        for ($i = 1; $i < 5; $i++) {
            $bus->emit('najm_hoda.response.ready', ['request_id' => (string) $i]);
        }
        $bus->emit('najm_hoda.response.failed', ['request_id' => '0']);

        $monitor = new NajmHodaOpsHealthMonitor($bus);
        $triage = new NajmHodaOpsTriageService($bus);

        $snapshot = $monitor->snapshot(15, 400);
        $triage->processSnapshot($snapshot, true);

        $skipped = $bus->recent('najm_hoda.ops.playbook.skipped', 10);
        $this->assertNotEmpty($skipped);

        $cooldownSkip = collect($skipped)->first(function (array $item) {
            return data_get($item, 'payload.action') === 'set_degraded_mode'
                && data_get($item, 'payload.reason') === 'cooldown_active';
        });
        $this->assertNotNull($cooldownSkip);

        $telemetry = $bus->recent('najm_hoda.ops.playbook.telemetry', 20);
        $this->assertNotEmpty($telemetry);

        $telemetryHit = collect($telemetry)->first(function (array $item) {
            return data_get($item, 'payload.action') === 'set_degraded_mode'
                && data_get($item, 'payload.outcome') === 'skipped'
                && data_get($item, 'payload.reason') === 'cooldown_active';
        });
        $this->assertNotNull($telemetryHit);
    }

    public function test_ops_monitor_command_emits_run_summary_and_caches_last_digest(): void
    {
        config([
            'najm-hoda.enabled' => true,
            'najm-hoda.runtime.event_bus.driver' => 'in_memory',
            'najm-hoda.runtime.ops.escalation.enabled' => false,
            'najm-hoda.runtime.ops.monitor.summary_ttl_minutes' => 180,
            'najm-hoda.runtime.ops.monitor.summary_history_size' => 2,
            'najm-hoda.runtime.ops.retention.telemetry_index_retention_hours' => 72,
            'najm-hoda.runtime.ops.retention.telemetry_index_max_size' => 5000,
        ]);

        $bus = app(\App\Services\NajmHoda\Runtime\RuntimeEventBus::class);
        $bus->clear();

        for ($i = 0; $i < 3; $i++) {
            $bus->emit('najm_hoda.request.received', ['request_id' => (string) $i]);
        }
        $bus->emit('najm_hoda.response.ready', ['request_id' => '1']);
        $bus->emit('najm_hoda.response.failed', ['request_id' => '2']);

        $this->artisan('najm-hoda:ops-monitor --dry-run --window=15 --limit=200')
            ->assertExitCode(0);
        $firstSummary = Cache::get('najm_hoda:ops:last_run_summary');

        $this->artisan('najm-hoda:ops-monitor --dry-run --window=15 --limit=200')
            ->assertExitCode(0);

        $summary = Cache::get('najm_hoda:ops:last_run_summary');
        $this->assertIsArray($summary);
        $this->assertArrayHasKey('run_id', $summary);
        $this->assertArrayHasKey('status', $summary);
        $this->assertArrayHasKey('incident_count', $summary);
        $this->assertArrayHasKey('escalation_count', $summary);
        $this->assertTrue((bool) ($summary['dry_run'] ?? false));

        $recent = $bus->recent('najm_hoda.ops.run.summary', 1);
        $this->assertNotEmpty($recent);
        $this->assertSame((string) ($summary['run_id'] ?? ''), (string) data_get($recent[0], 'payload.run_id'));

        $history = Cache::get('najm_hoda:ops:run_summary_history');
        $this->assertIsArray($history);
        $this->assertCount(2, $history);
        $this->assertSame((string) ($summary['run_id'] ?? ''), (string) ($history[0]['run_id'] ?? ''));
        $this->assertSame((string) ($firstSummary['run_id'] ?? ''), (string) ($history[1]['run_id'] ?? ''));
    }
}
