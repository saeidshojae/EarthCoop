<?php

namespace Tests\Feature\NajmHoda;

use App\Services\NajmHoda\FounderOps\FounderApprovalInboxService;
use App\Services\NajmHoda\FounderOps\FounderAttentionService;
use App\Services\NajmHoda\FounderOps\FounderExecutiveWorkQueueService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class FounderExecutiveWorkQueueServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_overdue_founder_decision_is_ranked_ahead_of_attention_items(): void
    {
        $attention = Mockery::mock(FounderAttentionService::class);
        $approvals = Mockery::mock(FounderApprovalInboxService::class);

        $attention->shouldReceive('brief')->once()->with(24)->andReturn([
            'items' => [[
                'priority' => 'P1',
                'domain' => 'groups',
                'title' => 'گروه نیازمند بررسی',
                'context' => ['entity_type' => 'group', 'entity_id' => 7],
            ]],
        ]);
        $approvals->shouldReceive('snapshot')->once()->with(100)->andReturn([
            'items' => [[
                'id' => 'approval-1',
                'risk' => 'medium',
                'sla_status' => 'overdue',
                'domain' => 'support',
                'domain_action' => 'send_reply',
                'context' => ['entity_type' => 'support_reply_draft', 'entity_id' => 11],
                'requested_at' => now()->subHour()->toIso8601String(),
                'deadline_at' => now()->subMinute()->toIso8601String(),
            ]],
        ]);

        $queue = (new FounderExecutiveWorkQueueService($attention, $approvals))->snapshot(24, 10);

        $this->assertSame(2, $queue['total']);
        $this->assertSame(1, $queue['needs_founder_decision']);
        $this->assertSame('approval', $queue['items'][0]['kind']);
        $this->assertSame('P0', $queue['items'][0]['priority']);
        $this->assertSame('approval-1', $queue['items'][0]['approval_request_id']);
    }
}
