<?php

namespace Tests\Feature\NajmHoda;

use App\Services\NajmHoda\FounderOps\FounderActionAuthorityService;
use App\Services\NajmHoda\FounderOps\FounderActionExecutionService;
use App\Services\NajmHoda\FounderOps\FounderActionOutcomeVerificationService;
use App\Services\NajmHoda\FounderOps\FounderApprovalVerifierService;
use App\Services\NajmHoda\FounderOps\FounderDelegationGrantService;
use App\Services\NajmHoda\Runtime\RuntimeEventBus;
use Tests\TestCase;

class FounderActionExecutionServiceTest extends TestCase
{
    protected function service($verifier, $delegations, $events): FounderActionExecutionService
    {
        $outcomes = $this->createMock(FounderActionOutcomeVerificationService::class);
        $outcomes->method('verify')->willReturn([
            'verified' => false,
            'status' => 'not_configured',
            'reason' => 'no_canonical_outcome_verifier',
        ]);

        return new FounderActionExecutionService(
            app(FounderActionAuthorityService::class),
            $delegations,
            $verifier,
            $outcomes,
            $events
        );
    }

    public function test_forbidden_action_never_invokes_callback(): void
    {
        $verifier = $this->createMock(FounderApprovalVerifierService::class);
        $delegations = $this->createMock(FounderDelegationGrantService::class);
        $events = $this->createMock(RuntimeEventBus::class);
        $events->expects($this->once())->method('emit')->with('najm_hoda.founder_ops.execution.blocked', $this->isType('array'));

        $called = false;
        $result = $this->service($verifier, $delegations, $events)->execute('governance', 'alter_vote', function () use (&$called) {
            $called = true;
            return 'should-not-run';
        });

        $this->assertFalse($called);
        $this->assertFalse($result['success']);
        $this->assertSame('blocked', $result['status']);
    }

    public function test_approval_required_action_is_blocked_without_request_id(): void
    {
        $verifier = $this->createMock(FounderApprovalVerifierService::class);
        $verifier->expects($this->never())->method('verify');
        $delegations = $this->createMock(FounderDelegationGrantService::class);
        $events = $this->createMock(RuntimeEventBus::class);
        $events->expects($this->once())->method('emit');

        $called = false;
        $result = $this->service($verifier, $delegations, $events)->execute('email', 'send_email', function () use (&$called) {
            $called = true;
        });

        $this->assertFalse($called);
        $this->assertSame('missing_approval_request', $result['reason']);
    }

    public function test_verified_founder_approval_allows_canonical_callback_to_run(): void
    {
        $verifier = $this->createMock(FounderApprovalVerifierService::class);
        $verifier->expects($this->once())->method('verify')->with('r1', 'email', 'send_email')
            ->willReturn(['valid' => true, 'reason' => 'verified_founder_approval', 'decision_by' => 10]);
        $delegations = $this->createMock(FounderDelegationGrantService::class);
        $events = $this->createMock(RuntimeEventBus::class);
        $events->expects($this->exactly(2))->method('emit');

        $result = $this->service($verifier, $delegations, $events)->execute('email', 'send_email', fn () => ['sent' => true], 'r1', [
            'entity_type' => 'email_template', 'entity_id' => 5, 'body' => 'must-not-enter-audit',
        ]);

        $this->assertTrue($result['success']);
        $this->assertSame(['sent' => true], $result['result']);
    }

    public function test_delegated_safe_action_is_blocked_without_active_grant(): void
    {
        $verifier = $this->createMock(FounderApprovalVerifierService::class);
        $delegations = $this->createMock(FounderDelegationGrantService::class);
        $delegations->expects($this->once())->method('isGranted')->with('support', 'classify_ticket')->willReturn(false);
        $events = $this->createMock(RuntimeEventBus::class);
        $events->expects($this->once())->method('emit');

        $called = false;
        $result = $this->service($verifier, $delegations, $events)->execute('support', 'classify_ticket', function () use (&$called) {
            $called = true;
        });

        $this->assertFalse($called);
        $this->assertSame('explicit_delegation_required', $result['reason']);
    }

    public function test_delegated_safe_action_executes_only_with_active_grant(): void
    {
        $verifier = $this->createMock(FounderApprovalVerifierService::class);
        $delegations = $this->createMock(FounderDelegationGrantService::class);
        $delegations->expects($this->once())->method('isGranted')->with('support', 'classify_ticket')->willReturn(true);
        $events = $this->createMock(RuntimeEventBus::class);
        $events->expects($this->exactly(2))->method('emit');

        $result = $this->service($verifier, $delegations, $events)->execute('support', 'classify_ticket', fn () => ['classified' => true]);

        $this->assertTrue($result['success']);
        $this->assertTrue($result['delegated']);
    }
}
