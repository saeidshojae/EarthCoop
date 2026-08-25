<?php

namespace Tests\Feature\NajmHoda;

use App\Services\NajmHoda\FounderOps\FounderApprovalInboxService;
use App\Services\NajmHoda\FounderOps\FounderAttentionService;
use App\Services\NajmHoda\FounderOps\FounderExecutiveWorkQueueService;
use App\Services\NajmHoda\FounderOps\FounderMinistryChatService;
use App\Services\NajmHoda\FounderOps\FounderOperationsSnapshotService;
use Mockery;
use Tests\TestCase;

class FounderMinistryChatServiceTest extends TestCase
{
    public function test_morning_brief_uses_canonical_founder_ops_read_models(): void
    {
        $attention = Mockery::mock(FounderAttentionService::class);
        $queue = Mockery::mock(FounderExecutiveWorkQueueService::class);
        $approvals = Mockery::mock(FounderApprovalInboxService::class);
        $snapshots = Mockery::mock(FounderOperationsSnapshotService::class);

        $attention->shouldReceive('brief')->once()->with(24)->andReturn([
            'generated_at' => '2026-08-25T08:00:00+00:00',
            'summary' => ['P0' => 1, 'P1' => 2, 'P2' => 3, 'P3' => 4],
        ]);
        $queue->shouldReceive('snapshot')->once()->with(24, 50)->andReturn([
            'needs_founder_decision' => 5,
            'prepared_by_najm_hoda' => 6,
            'attention_only' => 7,
            'items' => [
                ['kind' => 'approval', 'priority' => 'P0', 'domain' => 'support', 'title' => 'فوری'],
            ],
        ]);

        $service = new FounderMinistryChatService($attention, $queue, $approvals, $snapshots);
        $result = $service->respond('morning_brief', 24);

        $this->assertTrue($result['success']);
        $this->assertSame('morning_brief', data_get($result, 'management.intent'));
        $this->assertSame(3, data_get($result, 'management.summary_cards.urgent'));
        $this->assertSame(5, data_get($result, 'management.summary_cards.founder_decisions'));
        $this->assertSame(6, data_get($result, 'management.summary_cards.prepared'));
        $this->assertSame(7, data_get($result, 'management.summary_cards.information'));
        $this->assertStringContainsString('5 تصمیم منتظر شما', $result['message']);
    }

    public function test_communications_only_surfaces_communication_domains(): void
    {
        $attention = Mockery::mock(FounderAttentionService::class);
        $queue = Mockery::mock(FounderExecutiveWorkQueueService::class);
        $approvals = Mockery::mock(FounderApprovalInboxService::class);
        $snapshots = Mockery::mock(FounderOperationsSnapshotService::class);

        $queue->shouldReceive('snapshot')->once()->with(24, 100)->andReturn([
            'items' => [
                ['kind' => 'approval', 'domain' => 'email', 'priority' => 'P1'],
                ['kind' => 'proposal', 'domain' => 'blog', 'priority' => 'P2'],
                ['kind' => 'proposal', 'domain' => 'notifications', 'priority' => 'P2'],
                ['kind' => 'attention', 'domain' => 'stock', 'priority' => 'P0'],
            ],
        ]);

        $service = new FounderMinistryChatService($attention, $queue, $approvals, $snapshots);
        $result = $service->respond('communications', 24);

        $this->assertTrue($result['success']);
        $this->assertCount(3, data_get($result, 'management.items'));
        $this->assertSame(1, data_get($result, 'management.summary_cards.pending_decisions'));
        $this->assertSame(2, data_get($result, 'management.summary_cards.prepared'));
    }

    public function test_unknown_management_intent_fails_closed_without_calling_any_read_model(): void
    {
        $attention = Mockery::mock(FounderAttentionService::class);
        $queue = Mockery::mock(FounderExecutiveWorkQueueService::class);
        $approvals = Mockery::mock(FounderApprovalInboxService::class);
        $snapshots = Mockery::mock(FounderOperationsSnapshotService::class);

        $attention->shouldNotReceive('brief');
        $queue->shouldNotReceive('snapshot');
        $approvals->shouldNotReceive('snapshot');
        $snapshots->shouldNotReceive('snapshot');

        $service = new FounderMinistryChatService($attention, $queue, $approvals, $snapshots);
        $result = $service->respond('delete_everything', 24);

        $this->assertFalse($result['success']);
        $this->assertSame('unknown_management_intent', data_get($result, 'management.meta.reason'));
    }

    public function test_ministry_route_is_registered_under_founder_ops_boundary(): void
    {
        $this->assertSame(
            url('/admin/najm-hoda/founder-ops/ministry/chat'),
            route('admin.najm-hoda.founder-ops.ministry.chat')
        );
    }
}
