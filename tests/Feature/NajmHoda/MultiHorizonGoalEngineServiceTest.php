<?php

namespace Tests\Feature\NajmHoda;

use App\Services\NajmHoda\Runtime\InMemoryRuntimeEventBus;
use App\Services\NajmHoda\Runtime\NajmHodaEventCoverageKpiService;
use App\Services\NajmHoda\Runtime\NajmHodaMultiHorizonGoalEngineService;
use App\Services\NajmHoda\Runtime\NajmHodaUnifiedDomainKnowledgeGraphService;
use Tests\TestCase;

class MultiHorizonGoalEngineServiceTest extends TestCase
{
    public function test_build_backlog_generates_daily_goal_for_coverage_gap(): void
    {
        $bus = new InMemoryRuntimeEventBus(500);
        $bus->emit('najm_hoda.input.support.service.ticket_triage.succeeded', [
            'scope' => 'support',
            'risk' => 'low',
        ]);

        $service = $this->makeService($bus);
        $result = $service->buildBacklog([
            'scope' => 'global',
            'window_hours' => 24,
            'event_limit' => 500,
        ]);

        $this->assertContains('raise_critical_coverage', (array) data_get($result, 'horizons.daily', []));
        $this->assertGreaterThan(0, count((array) data_get($result, 'backlog', [])));
    }

    public function test_build_backlog_generates_pattern_driven_tasks(): void
    {
        $bus = new InMemoryRuntimeEventBus(500);
        $bus->emit('najm_hoda.input.support.service.ticket_triage.failed', [
            'scope' => 'support',
            'risk' => 'high',
            'ticket_id' => 301,
            'correlation_id' => 'c-support-1',
        ]);
        $bus->emit('najm_hoda.input.najm_bahar.service.project.update.failed', [
            'scope' => 'economy:najm-bahar',
            'risk' => 'medium',
            'project_id' => 401,
            'correlation_id' => 'c-project-1',
        ]);
        $bus->emit('najm_hoda.autonomy.ops.monitor.failed', [
            'scope' => 'ops',
            'risk' => 'high',
            'correlation_id' => 'c-ops-1',
        ]);

        $service = $this->makeService($bus);
        $result = $service->buildBacklog([
            'scope' => 'global',
            'window_hours' => 24,
            'event_limit' => 500,
        ]);

        $taskIds = array_map(static fn (array $row): string => (string) ($row['id'] ?? ''), (array) data_get($result, 'backlog', []));
        $this->assertTrue($this->containsPrefix($taskIds, 'escalate_support_ticket_301'));
        $this->assertTrue($this->containsPrefix($taskIds, 'review_project_delivery_401'));
        $this->assertTrue($this->containsPrefix($taskIds, 'open_ops_incident_c_ops_1'));
    }

    protected function makeService(InMemoryRuntimeEventBus $bus): NajmHodaMultiHorizonGoalEngineService
    {
        $kpi = new NajmHodaEventCoverageKpiService($bus);
        $graph = new NajmHodaUnifiedDomainKnowledgeGraphService($bus);

        return new NajmHodaMultiHorizonGoalEngineService($bus, $kpi, $graph);
    }

    /**
     * @param array<int, string> $ids
     */
    protected function containsPrefix(array $ids, string $prefix): bool
    {
        foreach ($ids as $id) {
            if (str_starts_with($id, $prefix)) {
                return true;
            }
        }

        return false;
    }
}

