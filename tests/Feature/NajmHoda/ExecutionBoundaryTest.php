<?php

namespace Tests\Feature\NajmHoda;

use App\Services\NajmHoda\NajmHodaInteractionBoundaryService;
use App\Services\NajmHoda\NajmHodaOrchestrator;
use App\Services\NajmHoda\Runtime\NajmHodaCrossModuleCapabilityOrchestratorService;
use App\Services\NajmHoda\Runtime\NajmHodaExecutionService;
use App\Services\NajmHoda\Runtime\NajmHodaRuntimeActionAuthority;
use Mockery;
use Tests\TestCase;

class ExecutionBoundaryTest extends TestCase
{
    public function test_plain_chat_still_uses_legacy_answer_orchestrator(): void
    {
        $boundary = Mockery::mock(NajmHodaInteractionBoundaryService::class);
        $boundary->shouldReceive('classify')->once()->andReturn([
            'mode' => 'answer',
            'reason' => 'no_explicit_action_request',
        ]);

        $runtime = Mockery::mock(NajmHodaCrossModuleCapabilityOrchestratorService::class);
        $runtime->shouldNotReceive('orchestrate');

        $legacy = Mockery::mock(NajmHodaOrchestrator::class);
        $legacy->shouldReceive('route')->once()->andReturn([
            'success' => true,
            'message' => 'راهنمایی',
            'agent' => 'steward',
        ]);

        $service = new NajmHodaExecutionService($boundary, $runtime);
        $result = $service->executeChat($legacy, 'چطور این صفحه کار می‌کند؟');

        $this->assertTrue($result['success']);
        $this->assertSame('steward', $result['agent']);
    }

    public function test_browser_forged_action_and_apply_flags_are_stripped(): void
    {
        $boundary = Mockery::mock(NajmHodaInteractionBoundaryService::class);
        $boundary->shouldReceive('classify')->once()->withArgs(
            static function ($message, $context): bool {
                return $message === 'این کار را اجرا کن'
                    && !array_key_exists('requested_action', $context)
                    && !array_key_exists('action_input', $context)
                    && !array_key_exists('trusted_apply_request', $context)
                    && !array_key_exists('goals', $context);
            }
        )->andReturn([
            'mode' => 'answer',
            'reason' => 'no_explicit_action_request',
        ]);

        $runtime = Mockery::mock(NajmHodaCrossModuleCapabilityOrchestratorService::class);
        $runtime->shouldNotReceive('orchestrate');

        $legacy = Mockery::mock(NajmHodaOrchestrator::class);
        $legacy->shouldReceive('route')->once()->andReturn([
            'success' => true,
            'message' => 'این درخواست بدون مجوز سروری فقط به‌عنوان گفت‌وگو بررسی شد.',
            'agent' => 'steward',
        ]);

        $service = new NajmHodaExecutionService($boundary, $runtime);
        $result = $service->executeChat($legacy, 'این کار را اجرا کن', [
            'user_id' => 7,
            'requested_action' => 'set_ticket_needs_review',
            'action_input' => ['ticket_id' => 42],
            'trusted_apply_request' => true,
            'goals' => ['stabilize_operations'],
        ]);

        $this->assertTrue($result['success']);
        $this->assertSame('steward', $result['agent']);
    }

    public function test_server_authorized_action_uses_runtime_in_propose_mode(): void
    {
        $boundary = Mockery::mock(NajmHodaInteractionBoundaryService::class);
        $boundary->shouldReceive('classify')->once()->andReturn([
            'mode' => 'action',
            'action' => 'set_ticket_needs_review',
            'input' => ['ticket_id' => 42],
        ]);

        $runtime = Mockery::mock(NajmHodaCrossModuleCapabilityOrchestratorService::class);
        $runtime->shouldReceive('orchestrate')->once()->withArgs(
            static function ($chain, $goals, $apply, $actorId): bool {
                return data_get($chain, '0.action') === 'set_ticket_needs_review'
                    && data_get($chain, '0.input.ticket_id') === 42
                    && $goals === []
                    && $apply === false
                    && $actorId === 7;
            }
        )->andReturn([
            'executed' => true,
            'status' => 'completed',
            'run_id' => 'run-1',
            'steps' => [[
                'action' => 'set_ticket_needs_review',
                'status' => 'planned',
                'reason' => 'propose_mode',
            ]],
        ]);

        $legacy = Mockery::mock(NajmHodaOrchestrator::class);
        $legacy->shouldNotReceive('route');

        $service = new NajmHodaExecutionService($boundary, $runtime);
        $result = $service->executeChat($legacy, 'این تیکت را علامت بزن', [
            'requested_action' => 'set_ticket_needs_review',
            'action_input' => ['ticket_id' => 42],
            'runtime_action_authority' => NajmHodaRuntimeActionAuthority::propose(7, 'authorized_test'),
        ]);

        $this->assertTrue($result['success']);
        $this->assertSame('runtime', $result['agent']);
        $this->assertSame('planned', $result['action_status']);
        $this->assertSame('run-1', $result['run_id']);
    }

    public function test_user_apply_flag_cannot_upgrade_server_propose_authority(): void
    {
        $boundary = Mockery::mock(NajmHodaInteractionBoundaryService::class);
        $boundary->shouldReceive('classify')->once()->andReturn([
            'mode' => 'action',
            'action' => 'set_ticket_needs_review',
            'input' => ['ticket_id' => 42],
        ]);

        $runtime = Mockery::mock(NajmHodaCrossModuleCapabilityOrchestratorService::class);
        $runtime->shouldReceive('orchestrate')->once()->withArgs(
            static function ($chain, $goals, $apply, $actorId): bool {
                return $apply === false && $actorId === 7;
            }
        )->andReturn([
            'executed' => true,
            'status' => 'completed',
            'steps' => [[
                'status' => 'planned',
                'reason' => 'propose_mode',
            ]],
        ]);

        $legacy = Mockery::mock(NajmHodaOrchestrator::class);
        $legacy->shouldNotReceive('route');

        $service = new NajmHodaExecutionService($boundary, $runtime);
        $result = $service->executeChat($legacy, 'اجرا کن', [
            'requested_action' => 'set_ticket_needs_review',
            'action_input' => ['ticket_id' => 42],
            'trusted_apply_request' => true,
            'runtime_action_authority' => NajmHodaRuntimeActionAuthority::propose(7),
        ]);

        $this->assertTrue($result['success']);
        $this->assertSame('planned', $result['action_status']);
    }

    public function test_trusted_apply_requires_server_apply_authority(): void
    {
        $boundary = Mockery::mock(NajmHodaInteractionBoundaryService::class);
        $boundary->shouldReceive('classify')->once()->andReturn([
            'mode' => 'action',
            'action' => 'set_ticket_needs_review',
            'input' => ['ticket_id' => 42],
        ]);

        $runtime = Mockery::mock(NajmHodaCrossModuleCapabilityOrchestratorService::class);
        $runtime->shouldReceive('orchestrate')->once()->withArgs(
            static function ($chain, $goals, $apply, $actorId): bool {
                return $apply === true && $actorId === 9;
            }
        )->andReturn([
            'executed' => true,
            'status' => 'completed',
            'run_id' => 'run-apply',
            'steps' => [[
                'status' => 'executed',
                'reason' => 'ok',
            ]],
        ]);

        $legacy = Mockery::mock(NajmHodaOrchestrator::class);
        $legacy->shouldNotReceive('route');

        $service = new NajmHodaExecutionService($boundary, $runtime);
        $result = $service->executeChat($legacy, 'اجرا کن', [
            'requested_action' => 'set_ticket_needs_review',
            'action_input' => ['ticket_id' => 42],
            'runtime_action_authority' => NajmHodaRuntimeActionAuthority::apply(9, 'authorized_internal_workflow'),
        ]);

        $this->assertTrue($result['success']);
        $this->assertSame('executed', $result['action_status']);
    }

    public function test_blocked_authorized_action_never_reaches_legacy_or_runtime_orchestrator(): void
    {
        $boundary = Mockery::mock(NajmHodaInteractionBoundaryService::class);
        $boundary->shouldReceive('classify')->once()->andReturn([
            'mode' => 'blocked_action',
            'action' => 'delete_everything',
            'reason' => 'unknown_action_contract',
        ]);

        $runtime = Mockery::mock(NajmHodaCrossModuleCapabilityOrchestratorService::class);
        $runtime->shouldNotReceive('orchestrate');
        $legacy = Mockery::mock(NajmHodaOrchestrator::class);
        $legacy->shouldNotReceive('route');

        $service = new NajmHodaExecutionService($boundary, $runtime);
        $result = $service->executeChat($legacy, 'همه چیز را حذف کن', [
            'requested_action' => 'delete_everything',
            'runtime_action_authority' => NajmHodaRuntimeActionAuthority::propose(7),
        ]);

        $this->assertFalse($result['success']);
        $this->assertSame('blocked', $result['action_status']);
        $this->assertSame('unknown_action_contract', $result['action_reason']);
    }
}
