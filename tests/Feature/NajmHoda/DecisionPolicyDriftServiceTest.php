<?php

namespace Tests\Feature\NajmHoda;

use App\Services\NajmHoda\Runtime\InMemoryRuntimeEventBus;
use App\Services\NajmHoda\Runtime\NajmHodaDecisionPolicyDriftService;
use Tests\TestCase;

class DecisionPolicyDriftServiceTest extends TestCase
{
    public function test_drift_report_calculates_rate_and_reasons(): void
    {
        config([
            'najm-hoda.runtime.autonomy.governance.drift.window_hours' => 24,
            'najm-hoda.runtime.autonomy.governance.drift.event_limit' => 500,
            'najm-hoda.runtime.autonomy.governance.kpis.policy_drift_rate.target_max' => 0.01,
            'najm-hoda.runtime.autonomy.governance.kpis.policy_drift_rate.warning_above' => 0.02,
        ]);

        $bus = new InMemoryRuntimeEventBus(500);
        $bus->emit('najm_hoda.autonomy.executor.executed', ['action' => 'run_ops_monitor']);
        $bus->emit('najm_hoda.autonomy.executor.executed', ['action' => 'run_ops_monitor']);
        $bus->emit('najm_hoda.autonomy.contract.accepted', ['action' => 'run_ops_monitor']);
        $bus->emit('najm_hoda.autonomy.safety.blocked', ['reason' => 'high_risk_blocked']);
        $bus->emit('najm_hoda.autonomy.contract.rejected', ['reason' => 'policy_conflict']);
        $bus->emit('najm_hoda.autonomy.plan_item.blocked', ['reason' => 'policy_conflict']);

        $service = new NajmHodaDecisionPolicyDriftService($bus);
        $report = $service->report(24);

        $this->assertSame(3, (int) ($report['total_decisions'] ?? 0));
        $this->assertSame(3, (int) ($report['drift_count'] ?? 0));
        $this->assertSame(1.0, (float) ($report['drift_rate'] ?? 0.0));
        $this->assertSame('breach', (string) ($report['status'] ?? ''));
        $this->assertSame('policy_conflict', (string) data_get($report, 'top_reasons.0.reason'));
        $this->assertSame(2, (int) data_get($report, 'top_reasons.0.count'));

        $events = $bus->recent('najm_hoda.autonomy.governance.drift.reported', 1);
        $this->assertCount(1, $events);
    }
}
