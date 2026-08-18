<?php

namespace Tests\Feature\NajmHoda;

use App\Models\ChatRequest;
use App\Models\Group;
use App\Models\GroupSession;
use App\Models\NajmHodaGroupActionItem;
use App\Models\NajmHodaGroupMeetingMinute;
use App\Services\NajmHoda\NajmHodaGroupManagementSnapshotService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class GroupManagementSnapshotServiceTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        if (! Schema::hasTable('najm_hoda_group_meeting_minutes')) {
            Artisan::call('migrate', [
                '--path' => 'database/migrations/2026_08_18_010500_create_najm_hoda_group_meeting_minutes_table.php',
                '--force' => true,
            ]);
        }
    }

    public function test_snapshot_counts_only_current_group_management_work(): void
    {
        $group = Group::create(['name' => 'Snapshot group ' . uniqid('', true), 'is_open' => 1]);
        $other = Group::create(['name' => 'Other group ' . uniqid('', true), 'is_open' => 1]);

        $active = GroupSession::create([
            'group_id' => $group->id, 'created_by' => null, 'title' => 'نشست فعال',
            'status' => 'active', 'starts_at' => now()->subHour(), 'started_at' => now()->subHour(),
        ]);
        GroupSession::create([
            'group_id' => $group->id, 'created_by' => null, 'title' => 'نشست آینده',
            'status' => 'scheduled', 'starts_at' => now()->addDay(),
        ]);
        GroupSession::create([
            'group_id' => $other->id, 'created_by' => null, 'title' => 'نشست گروه دیگر',
            'status' => 'scheduled', 'starts_at' => now()->addDay(),
        ]);

        NajmHodaGroupMeetingMinute::create([
            'group_session_id' => $active->id,
            'group_id' => $group->id,
            'status' => 'draft',
            'decision_candidates' => [
                ['title' => 'در انتظار', 'state' => 'candidate'],
                ['title' => 'تأیید شده', 'state' => 'confirmed'],
            ],
            'action_candidates' => [],
            'evidence_snapshot' => [],
        ]);

        NajmHodaGroupActionItem::create(['group_id' => $group->id, 'title' => 'باز', 'status' => 'open', 'priority' => 'medium']);
        NajmHodaGroupActionItem::create(['group_id' => $group->id, 'title' => 'مسدود', 'status' => 'blocked', 'priority' => 'high']);
        NajmHodaGroupActionItem::create(['group_id' => $group->id, 'title' => 'تمام', 'status' => 'done', 'priority' => 'low']);
        NajmHodaGroupActionItem::create(['group_id' => $other->id, 'title' => 'گروه دیگر', 'status' => 'open', 'priority' => 'medium']);

        ChatRequest::create([
            'sender_id' => null, 'receiver_id' => null, 'request_to_group' => $group->id,
            'status' => 'pending', 'message' => 'درخواست گروه',
        ]);

        $snapshot = app(NajmHodaGroupManagementSnapshotService::class)->snapshot($group, 3);

        $this->assertSame(1, $snapshot['sessions']['active_count']);
        $this->assertSame(1, $snapshot['sessions']['scheduled_count']);
        $this->assertSame('نشست فعال', $snapshot['sessions']['active']['title']);
        $this->assertSame(1, $snapshot['minutes']['draft_count']);
        $this->assertSame(1, $snapshot['minutes']['pending_decisions']);
        $this->assertSame(2, $snapshot['actions']['active_count']);
        $this->assertSame(1, $snapshot['actions']['blocked']);
        $this->assertSame(1, $snapshot['actions']['done']);
        $this->assertSame(1, $snapshot['requests']['pending_group_chat']);
    }

    public function test_inspector_snapshot_does_not_surface_manager_chat_request_queue(): void
    {
        $group = Group::create(['name' => 'Inspector snapshot ' . uniqid('', true), 'is_open' => 1]);
        ChatRequest::create([
            'sender_id' => null, 'receiver_id' => null, 'request_to_group' => $group->id,
            'status' => 'pending', 'message' => 'مدیر باید رسیدگی کند',
        ]);

        $snapshot = app(NajmHodaGroupManagementSnapshotService::class)->snapshot($group, 2);

        $this->assertSame(0, $snapshot['requests']['pending_group_chat']);
    }
}
