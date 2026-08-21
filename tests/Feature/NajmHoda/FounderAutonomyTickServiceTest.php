<?php

namespace Tests\Feature\NajmHoda;

use App\Services\NajmHoda\FounderOps\FounderActionExecutionService;
use App\Services\NajmHoda\FounderOps\FounderAutonomyBridgeService;
use App\Services\NajmHoda\FounderOps\FounderAutonomyTickService;
use App\Services\NajmHoda\FounderOps\FounderLowRiskDomainActionService;
use App\Services\NajmHoda\Runtime\RuntimeEventBus;
use Mockery;
use Tests\TestCase;

class FounderAutonomyTickServiceTest extends TestCase
{
    public function test_proposal_only_action_is_materialized_only_through_supported_low_risk_handler(): void
    {
        $bridge = Mockery::mock(FounderAutonomyBridgeService::class);
        $execution = Mockery::mock(FounderActionExecutionService::class);
        $handlers = Mockery::mock(FounderLowRiskDomainActionService::class);
        $events = Mockery::mock(RuntimeEventBus::class);

        $bridge->shouldReceive('plan')->once()->with(24, 12)->andReturn([
            'actions' => [[
                'domain' => 'support',
                'action' => 'draft_reply',
                'action_context' => [
                    'entity_type' => 'ticket',
                    'entity_id' => 55,
                    'reason_code' => 'support-draft-55',
                ],
                'preparation' => ['status' => 'proposal_only'],
            ]],
        ]);

        $handlers->shouldReceive('supports')->once()->with('support', 'draft_reply')->andReturnTrue();
        $handlers->shouldReceive('execute')->once()->with('support', 'draft_reply', Mockery::type('array'))->andReturn([
            'success' => true,
            'status' => 'drafted',
            'draft_id' => 91,
        ]);
        $execution->shouldNotReceive('execute');
        $events->shouldReceive('emit')->once()->with('najm_hoda.founder_ops.autonomy.tick.completed', Mockery::on(
            fn (array $summary): bool => ($summary['proposal_materialized'] ?? 0) === 1
                && ($summary['executed'] ?? 0) === 0
        ));

        $result = (new FounderAutonomyTickService($bridge, $execution, $handlers, $events))->run();

        $this->assertSame(1, $result['summary']['proposal_materialized']);
        $this->assertSame(0, $result['summary']['executed']);
        $this->assertSame('proposal_materialized', $result['results'][0]['status']);
        $this->assertSame(91, $result['results'][0]['result']['draft_id']);
    }

    public function test_unsupported_proposal_remains_fail_closed_and_is_not_executed(): void
    {
        $bridge = Mockery::mock(FounderAutonomyBridgeService::class);
        $execution = Mockery::mock(FounderActionExecutionService::class);
        $handlers = Mockery::mock(FounderLowRiskDomainActionService::class);
        $events = Mockery::mock(RuntimeEventBus::class);

        $bridge->shouldReceive('plan')->once()->andReturn([
            'actions' => [[
                'domain' => 'admin_settings',
                'action' => 'recommend_change',
                'action_context' => ['reason_code' => 'admin-proposal'],
                'preparation' => ['status' => 'proposal_only'],
            ]],
        ]);

        $handlers->shouldReceive('supports')->once()->with('admin_settings', 'recommend_change')->andReturnFalse();
        $handlers->shouldNotReceive('execute');
        $execution->shouldNotReceive('execute');
        $events->shouldReceive('emit')->once();

        $result = (new FounderAutonomyTickService($bridge, $execution, $handlers, $events))->run();

        $this->assertSame(1, $result['summary']['proposal_not_materialized']);
        $this->assertSame('proposal_not_materialized', $result['results'][0]['status']);
        $this->assertSame('no_canonical_low_risk_handler', $result['results'][0]['reason']);
    }
}
