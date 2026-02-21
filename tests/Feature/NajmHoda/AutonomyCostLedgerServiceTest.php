<?php

namespace Tests\Feature\NajmHoda;

use App\Services\NajmHoda\Runtime\InMemoryRuntimeEventBus;
use App\Services\NajmHoda\Runtime\NajmHodaAutonomyCostLedgerService;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class AutonomyCostLedgerServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config([
            'cache.default' => 'array',
            'najm-hoda.runtime.autonomy.costs.daily_budget' => 0.01,
            'najm-hoda.runtime.autonomy.costs.monthly_budget' => 0.1,
            'najm-hoda.runtime.autonomy.costs.default_action_cost' => 0.003,
            'najm-hoda.runtime.autonomy.costs.action_estimates.run_ops_monitor' => 0.004,
        ]);
        Cache::flush();
    }

    public function test_cost_ledger_records_totals_and_enforces_budget(): void
    {
        $bus = new InMemoryRuntimeEventBus(100);
        $service = new NajmHodaAutonomyCostLedgerService($bus);

        $service->record('run_ops_monitor', 0.004);
        $service->record('run_ops_monitor', 0.004);

        $status = $service->status();
        $this->assertSame(0.008, (float) ($status['daily_total'] ?? 0.0));

        $check = $service->canSpend(0.003);
        $this->assertFalse((bool) ($check['allowed'] ?? true));
        $this->assertSame('daily_budget_exceeded', (string) ($check['reason'] ?? ''));

        $events = $bus->recent('najm_hoda.autonomy.cost.recorded', 2);
        $this->assertCount(2, $events);
    }
}
