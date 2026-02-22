<?php

namespace Tests\Feature\NajmHoda;

use App\Services\NajmHoda\Runtime\InMemoryRuntimeEventBus;
use App\Services\NajmHoda\Runtime\NajmHodaAutonomyControlService;
use App\Services\NajmHoda\Runtime\NajmHodaContinuousEvaluationHarnessService;
use App\Services\NajmHoda\Runtime\NajmHodaGovernanceAlertingService;
use App\Services\NajmHoda\Runtime\NajmHodaOperationalAutonomyActivationService;
use App\Services\NajmHoda\Runtime\NajmHodaRunbookRegistryService;
use App\Services\NajmHoda\Runtime\NajmHodaSafeCodeOpsCanaryService;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class OperationalAutonomyActivationServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config([
            'cache.default' => 'array',
            'najm-hoda.runtime.autonomy.operations_24x7.enabled' => true,
            'najm-hoda.runtime.autonomy.operations_24x7.stop.after_consecutive_breaches' => 2,
            'najm-hoda.runtime.autonomy.runbooks.registry' => [
                [
                    'id' => 'incident_response',
                    'status' => 'active',
                    'checklist' => ['a', 'b', 'c', 'd'],
                ],
                [
                    'id' => 'degraded_mode',
                    'status' => 'active',
                    'checklist' => ['a', 'b', 'c', 'd'],
                ],
                [
                    'id' => 'override_control',
                    'status' => 'active',
                    'checklist' => ['a', 'b', 'c', 'd'],
                ],
                [
                    'id' => 'recovery_validation',
                    'status' => 'active',
                    'checklist' => ['a', 'b', 'c', 'd'],
                ],
            ],
        ]);
        Cache::flush();
    }

    public function test_tick_warning_executes_degraded_mode_runbook(): void
    {
        $bus = new InMemoryRuntimeEventBus(300);
        $evaluation = \Mockery::mock(NajmHodaContinuousEvaluationHarnessService::class);
        $evaluation->shouldReceive('run')->once()->andReturn([
            'status' => 'warning',
            'alert_count' => 1,
        ]);
        $canary = \Mockery::mock(NajmHodaSafeCodeOpsCanaryService::class);
        $canary->shouldReceive('evaluate')->once()->andReturn([
            'success' => true,
            'state' => ['status' => 'canary'],
        ]);
        $alerting = \Mockery::mock(NajmHodaGovernanceAlertingService::class);
        $alerting->shouldReceive('evaluateAndAlert')->once()->andReturn([
            'count' => 1,
            'alerts' => [['severity' => 'warning']],
        ]);

        $control = new NajmHodaAutonomyControlService($bus);
        $runbooks = new NajmHodaRunbookRegistryService($bus);
        $service = new NajmHodaOperationalAutonomyActivationService(
            $bus,
            $evaluation,
            $canary,
            $alerting,
            $control,
            $runbooks
        );

        $activated = $service->activate(11, 'always', 'test_activate');
        $this->assertTrue((bool) ($activated['success'] ?? false));

        $tick = $service->tick(11, true, 24);
        $this->assertTrue((bool) ($tick['success'] ?? false));
        $this->assertSame('warning', (string) ($tick['status'] ?? 'ok'));
        $this->assertSame('executed', (string) data_get($tick, 'runbook_execution.0.status', ''));
        $this->assertSame('degraded_mode', (string) data_get($tick, 'runbook_execution.0.runbook_id', ''));
        $this->assertSame('propose', (string) data_get($control->override(), 'force_mode', ''));
    }

    public function test_tick_halts_operations_on_consecutive_breach_threshold(): void
    {
        config(['najm-hoda.runtime.autonomy.operations_24x7.stop.after_consecutive_breaches' => 1]);

        $bus = new InMemoryRuntimeEventBus(300);
        $evaluation = \Mockery::mock(NajmHodaContinuousEvaluationHarnessService::class);
        $evaluation->shouldReceive('run')->once()->andReturn([
            'status' => 'breach',
            'alert_count' => 3,
        ]);
        $canary = \Mockery::mock(NajmHodaSafeCodeOpsCanaryService::class);
        $canary->shouldReceive('evaluate')->once()->andReturn([
            'success' => true,
            'state' => ['status' => 'rolled_back'],
        ]);
        $alerting = \Mockery::mock(NajmHodaGovernanceAlertingService::class);
        $alerting->shouldReceive('evaluateAndAlert')->once()->andReturn([
            'count' => 1,
            'alerts' => [['severity' => 'critical']],
        ]);

        $control = new NajmHodaAutonomyControlService($bus);
        $runbooks = new NajmHodaRunbookRegistryService($bus);
        $service = new NajmHodaOperationalAutonomyActivationService(
            $bus,
            $evaluation,
            $canary,
            $alerting,
            $control,
            $runbooks
        );

        $service->activate(12, 'always', 'test_activate');
        $tick = $service->tick(12, true, 24);

        $this->assertTrue((bool) ($tick['success'] ?? false));
        $this->assertTrue((bool) ($tick['halted'] ?? false));
        $this->assertSame('halted', (string) data_get($tick, 'state.status', 'active'));
        $this->assertTrue((bool) ($control->killSwitchState()['active'] ?? false));
    }
}

