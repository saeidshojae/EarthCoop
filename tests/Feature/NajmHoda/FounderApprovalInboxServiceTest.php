<?php

namespace Tests\Feature\NajmHoda;

use App\Services\NajmHoda\FounderOps\FounderApprovalInboxService;
use App\Services\NajmHoda\Runtime\NajmHodaAutonomyApprovalService;
use Tests\TestCase;

class FounderApprovalInboxServiceTest extends TestCase
{
    public function test_inbox_only_surfaces_founder_operations_approvals(): void
    {
        $approvals = $this->createMock(NajmHodaAutonomyApprovalService::class);
        $approvals->method('pending')->willReturn([
            [
                'id' => 'f1',
                'status' => 'pending',
                'action' => 'founder_ops:email.send_email',
                'risk' => 'high',
                'requested_at' => now()->toIso8601String(),
                'deadline_at' => now()->addMinutes(20)->toIso8601String(),
                'sla_status' => 'within_sla',
                'plan_item' => ['domain' => 'email', 'domain_action' => 'send_email'],
                'context' => ['entity_id' => 5],
            ],
            [
                'id' => 'other',
                'status' => 'pending',
                'action' => 'runtime:code.patch',
                'risk' => 'medium',
                'sla_status' => 'overdue',
                'plan_item' => [],
                'context' => [],
            ],
        ]);

        $snapshot = (new FounderApprovalInboxService($approvals))->snapshot();

        $this->assertSame(1, $snapshot['pending']);
        $this->assertSame(0, $snapshot['overdue']);
        $this->assertSame(1, $snapshot['within_sla']);
        $this->assertSame('email', data_get($snapshot, 'items.0.domain'));
    }
}
