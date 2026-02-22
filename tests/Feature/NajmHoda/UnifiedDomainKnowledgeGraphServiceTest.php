<?php

namespace Tests\Feature\NajmHoda;

use App\Models\User;
use App\Services\NajmHoda\Runtime\InMemoryRuntimeEventBus;
use App\Services\NajmHoda\Runtime\NajmHodaUnifiedDomainKnowledgeGraphService;
use Tests\TestCase;

class UnifiedDomainKnowledgeGraphServiceTest extends TestCase
{
    public function test_query_returns_traceable_context_with_runtime_signals(): void
    {
        $bus = new InMemoryRuntimeEventBus(200);
        $bus->emit('najm_hoda.input.support.service.ticket_triage.succeeded', [
            'scope' => 'support',
            'risk' => 'low',
        ]);

        $service = new NajmHodaUnifiedDomainKnowledgeGraphService($bus);
        $result = $service->query([
            'scope' => 'global',
            'limit' => 20,
        ]);

        $this->assertNotEmpty((string) data_get($result, 'trace.trace_id'));
        $this->assertSame('global', (string) data_get($result, 'trace.effective_scope'));
        $this->assertSame('overview', (string) data_get($result, 'trace.query_profile'));
        $this->assertIsArray(data_get($result, 'nodes.runtime_signals'));
    }

    public function test_non_admin_actor_scope_is_reduced_from_global_to_actor(): void
    {
        $bus = new InMemoryRuntimeEventBus(50);
        $service = new NajmHodaUnifiedDomainKnowledgeGraphService($bus);

        $actor = \Mockery::mock(User::class)->makePartial();
        $actor->id = 15;
        $actor->is_admin = 0;
        $actor->shouldReceive('hasRole')->with('super-admin')->andReturn(false);

        $result = $service->query([
            'actor' => $actor,
            'scope' => 'global',
            'limit' => 10,
        ]);

        $this->assertSame('actor', (string) data_get($result, 'trace.effective_scope'));
        $this->assertTrue((bool) data_get($result, 'trace.scope_reduced_by_rbac'));
        $this->assertSame(15, (int) data_get($result, 'trace.actor_id'));
    }

    public function test_project_delivery_profile_excludes_tickets_and_keeps_projects_domain(): void
    {
        $bus = new InMemoryRuntimeEventBus(50);
        $service = new NajmHodaUnifiedDomainKnowledgeGraphService($bus);

        $result = $service->query([
            'scope' => 'global',
            'profile' => 'project_delivery',
            'limit' => 10,
        ]);

        $this->assertSame('project_delivery', (string) data_get($result, 'trace.query_profile'));
        $this->assertIsArray((array) data_get($result, 'nodes.projects'));
        $this->assertSame([], (array) data_get($result, 'nodes.tickets'));
    }

    public function test_runtime_signal_edges_include_domain_entity_and_correlation_semantics(): void
    {
        $bus = new InMemoryRuntimeEventBus(200);
        $bus->emit('najm_hoda.input.support.service.ticket_triage.failed', [
            'scope' => 'support',
            'risk' => 'medium',
            'user_id' => 31,
            'group_id' => 14,
            'project_id' => 45,
            'ticket_id' => 99,
            'correlation_id' => 'corr-x',
        ]);
        $bus->emit('najm_hoda.input.support.service.ticket_triage.rejected', [
            'scope' => 'support',
            'risk' => 'high',
            'ticket_id' => 99,
            'correlation_id' => 'corr-x',
        ]);

        $service = new NajmHodaUnifiedDomainKnowledgeGraphService($bus);
        $result = $service->query([
            'scope' => 'global',
            'profile' => 'ops_triage',
            'limit' => 20,
        ]);

        $this->assertGreaterThan(0, count((array) data_get($result, 'nodes.runtime_signals', [])));

        $edges = (array) data_get($result, 'edges', []);
        $this->assertTrue($this->hasEdge($edges, 'affects_ticket', 'ticket:99'));
        $this->assertTrue($this->hasEdge($edges, 'affects_project', 'project:45'));
        $this->assertTrue($this->hasEdge($edges, 'observes_user_context', 'user:31'));
        $this->assertTrue($this->hasEdgeType($edges, 'correlates_with'));
    }

    public function test_decision_patterns_are_built_for_support_project_and_ops_chains(): void
    {
        $bus = new InMemoryRuntimeEventBus(200);
        $bus->emit('najm_hoda.input.support.service.ticket_triage.failed', [
            'scope' => 'support',
            'risk' => 'high',
            'ticket_id' => 201,
            'correlation_id' => 'corr-support-1',
        ]);
        $bus->emit('najm_hoda.input.najm_bahar.service.project.update.failed', [
            'scope' => 'economy:najm-bahar',
            'risk' => 'medium',
            'project_id' => 77,
            'correlation_id' => 'corr-project-1',
        ]);
        $bus->emit('najm_hoda.autonomy.ops.monitor.failed', [
            'scope' => 'ops',
            'risk' => 'high',
            'correlation_id' => 'corr-ops-1',
        ]);
        $bus->emit('najm_hoda.autonomy.governance.alert.raised', [
            'scope' => 'autonomy',
            'risk' => 'medium',
            'correlation_id' => 'corr-ops-1',
        ]);

        $service = new NajmHodaUnifiedDomainKnowledgeGraphService($bus);
        $result = $service->query([
            'scope' => 'global',
            'profile' => 'ops_triage',
            'limit' => 20,
        ]);

        $this->assertSame(201, (int) data_get($result, 'patterns.support_escalation_candidates.0.ticket_id'));
        $this->assertSame(77, (int) data_get($result, 'patterns.project_delivery_risk_hotspots.0.project_id'));
        $this->assertGreaterThan(0, count((array) data_get($result, 'patterns.ops_alert_chains', [])));
    }

    /**
     * @param array<int, array<string, mixed>> $edges
     */
    protected function hasEdge(array $edges, string $type, string $to): bool
    {
        foreach ($edges as $edge) {
            if ((string) ($edge['type'] ?? '') === $type && (string) ($edge['to'] ?? '') === $to) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<int, array<string, mixed>> $edges
     */
    protected function hasEdgeType(array $edges, string $type): bool
    {
        foreach ($edges as $edge) {
            if ((string) ($edge['type'] ?? '') === $type) {
                return true;
            }
        }

        return false;
    }
}
