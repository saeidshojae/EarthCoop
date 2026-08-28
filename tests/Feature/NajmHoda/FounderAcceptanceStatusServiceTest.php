<?php

namespace Tests\Feature\NajmHoda;

use App\Services\NajmHoda\FounderOps\FounderAcceptanceStatusService;
use App\Services\NajmHoda\Runtime\RuntimeEventBus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FounderAcceptanceStatusServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_snapshot_distinguishes_verified_pending_and_review_required_executions(): void
    {
        $events = app(RuntimeEventBus::class);
        $events->clear();

        $events->emit('najm_hoda.founder_ops.execution.completed', [
            'domain' => 'notifications',
            'action' => 'publish_announcement',
            'mode' => 'approval_required',
            'outcome_verified' => true,
            'verification_status' => 'verified',
            'context' => ['entity_type' => 'founder_announcement_draft', 'entity_id' => 10],
        ]);
        $events->emit('najm_hoda.founder_ops.execution.completed', [
            'domain' => 'admin_settings',
            'action' => 'change_setting',
            'mode' => 'approval_required',
            'outcome_verified' => false,
            'verification_status' => 'not_configured',
        ]);
        $events->emit('najm_hoda.founder_ops.execution.completed', [
            'domain' => 'support',
            'action' => 'send_reply',
            'mode' => 'approval_required',
            'outcome_verified' => false,
            'verification_status' => 'failed',
        ]);

        $snapshot = app(FounderAcceptanceStatusService::class)->snapshot(24, 20);

        $this->assertSame(3, data_get($snapshot, 'counts.executed'));
        $this->assertSame(1, data_get($snapshot, 'counts.verified'));
        $this->assertSame(1, data_get($snapshot, 'counts.verification_pending'));
        $this->assertSame(1, data_get($snapshot, 'counts.needs_review'));

        $byAction = collect($snapshot['items'])->keyBy('action');
        $this->assertSame('verified', $byAction['publish_announcement']['acceptance']);
        $this->assertSame('verification_pending', $byAction['change_setting']['acceptance']);
        $this->assertSame('needs_review', $byAction['send_reply']['acceptance']);
    }
}
