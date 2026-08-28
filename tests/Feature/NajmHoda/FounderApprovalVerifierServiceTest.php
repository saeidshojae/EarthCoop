<?php

namespace Tests\Feature\NajmHoda;

use App\Services\NajmHoda\FounderOps\FounderApprovalVerifierService;
use App\Services\NajmHoda\Runtime\NajmHodaAutonomyApprovalService;
use Tests\TestCase;

class FounderApprovalVerifierServiceTest extends TestCase
{
    public function test_approval_fails_closed_when_founder_identity_is_not_configured(): void
    {
        config()->set('najm-hoda-founder-action-policy.founder_approval.user_ids', []);
        $approvals = $this->createMock(NajmHodaAutonomyApprovalService::class);
        $approvals->expects($this->never())->method('history');

        $result = (new FounderApprovalVerifierService($approvals))->verify('r1', 'email', 'send_email');

        $this->assertFalse($result['valid']);
        $this->assertSame('founder_approver_not_configured', $result['reason']);
    }

    public function test_approval_by_non_founder_admin_is_rejected(): void
    {
        config()->set('najm-hoda-founder-action-policy.founder_approval.user_ids', [10]);
        $approvals = $this->createMock(NajmHodaAutonomyApprovalService::class);
        $approvals->method('history')->willReturn([[
            'id' => 'r1',
            'status' => 'approved',
            'action' => 'founder_ops:email.send_email',
            'decision_by' => 99,
            'decision_at' => now()->toIso8601String(),
        ]]);

        $result = (new FounderApprovalVerifierService($approvals))->verify('r1', 'email', 'send_email');

        $this->assertFalse($result['valid']);
        $this->assertSame('decision_not_by_authorized_founder', $result['reason']);
    }

    public function test_exact_action_approved_by_configured_founder_is_valid(): void
    {
        config()->set('najm-hoda-founder-action-policy.founder_approval.user_ids', [10]);
        $approvals = $this->createMock(NajmHodaAutonomyApprovalService::class);
        $approvals->method('history')->willReturn([[
            'id' => 'r1',
            'status' => 'approved',
            'action' => 'founder_ops:email.send_email',
            'decision_by' => 10,
            'decision_at' => now()->toIso8601String(),
        ]]);

        $result = (new FounderApprovalVerifierService($approvals))->verify('r1', 'email', 'send_email');

        $this->assertTrue($result['valid']);
        $this->assertSame(10, $result['decision_by']);
    }
}
