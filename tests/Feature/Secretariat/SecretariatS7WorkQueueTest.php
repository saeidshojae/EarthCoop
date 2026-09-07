<?php

namespace Tests\Feature\Secretariat;

use App\Models\Group;
use App\Models\GroupUser;
use App\Models\User;
use App\Modules\Secretariat\Services\SecretariatDispatchService;
use App\Modules\Secretariat\Services\SecretariatOfficeService;
use App\Modules\Secretariat\Services\SecretariatRecordService;
use App\Modules\Secretariat\Services\SecretariatRelationService;
use App\Modules\Secretariat\Services\SecretariatWorkQueueService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SecretariatS7WorkQueueTest extends TestCase
{
    use RefreshDatabase;

    public function test_explicit_deadline_follow_up_and_response_expectation_drive_queue_without_age_heuristics(): void
    {
        [$admin, $manager, $office] = $this->officeFixture();
        $record = $this->formalRecord($office, $admin, 'Awaiting reply');
        $unscheduled = $this->formalRecord($office, $admin, 'No invented deadline');

        $target = User::factory()->create();
        GroupUser::query()->create(['group_id' => $office->scope_id, 'user_id' => $target->id, 'role' => 0, 'status' => 1]);

        $dispatch = app(SecretariatDispatchService::class)->create($record, $admin, [
            'dispatch_type' => 'referral',
            'channel' => 'internal',
            'target_user_id' => $target->id,
            'expects_response' => true,
            'due_at' => now()->subDay(),
            'follow_up_at' => now()->subHours(2),
        ]);
        app(SecretariatDispatchService::class)->create($unscheduled, $admin, [
            'dispatch_type' => 'referral',
            'channel' => 'internal',
            'target_user_id' => $target->id,
            'expects_response' => false,
        ]);

        $queue = app(SecretariatWorkQueueService::class)->forOffice($office, $manager);

        $this->assertSame([$dispatch->id], collect($queue['overdue_dispatches'])->pluck('dispatch_id')->all());
        $this->assertSame([$dispatch->id], collect($queue['follow_up_due'])->pluck('dispatch_id')->all());
        $this->assertSame([$dispatch->id], collect($queue['unanswered_correspondence'])->pluck('dispatch_id')->all());
        $this->assertNotContains($unscheduled->id, collect($queue['overdue_dispatches'])->pluck('record_id')->all());
    }

    public function test_visible_responds_to_relation_resolves_unanswered_without_hiding_overdue_deadline(): void
    {
        [$admin, $manager, $office] = $this->officeFixture();
        $record = $this->formalRecord($office, $admin, 'Question');
        $reply = $this->formalRecord($office, $admin, 'Reply');
        $target = User::factory()->create();
        GroupUser::query()->create(['group_id' => $office->scope_id, 'user_id' => $target->id, 'role' => 0, 'status' => 1]);

        $dispatch = app(SecretariatDispatchService::class)->create($record, $admin, [
            'dispatch_type' => 'delivery',
            'channel' => 'internal',
            'target_user_id' => $target->id,
            'expects_response' => true,
            'due_at' => now()->subHour(),
        ]);
        app(SecretariatRelationService::class)->add($reply, $record, 'responds_to', $admin);

        $queue = app(SecretariatWorkQueueService::class)->forOffice($office, $manager);

        $this->assertNotContains($dispatch->id, collect($queue['unanswered_correspondence'])->pluck('dispatch_id')->all());
        $this->assertContains($dispatch->id, collect($queue['overdue_dispatches'])->pluck('dispatch_id')->all());
    }

    public function test_pending_approval_requires_register_authority_and_confidential_dispatch_does_not_leak_without_acl(): void
    {
        [$admin, $manager, $office, $inspector] = $this->officeFixture(true);
        $records = app(SecretariatRecordService::class);

        $pending = $records->createDraft($office, $admin, [
            'record_type' => 'official_note', 'direction' => 'internal', 'title' => 'Pending approval',
            'body' => 'Body', 'confidentiality' => 'office_members',
        ]);
        $records->submitForApproval($pending, $admin);

        $confidential = $this->formalRecord($office, $admin, 'Hidden confidential', 'confidential');
        $target = User::factory()->create();
        GroupUser::query()->create(['group_id' => $office->scope_id, 'user_id' => $target->id, 'role' => 0, 'status' => 1]);
        app(SecretariatDispatchService::class)->create($confidential, $admin, [
            'dispatch_type' => 'referral', 'channel' => 'internal', 'target_user_id' => $target->id,
            'expects_response' => true, 'due_at' => now()->subDay(),
        ]);

        $managerQueue = app(SecretariatWorkQueueService::class)->forOffice($office, $manager);
        $inspectorQueue = app(SecretariatWorkQueueService::class)->forOffice($office, $inspector);

        $this->assertContains($pending->id, collect($managerQueue['pending_approval'])->pluck('record_id')->all());
        $this->assertNotContains($pending->id, collect($inspectorQueue['pending_approval'])->pluck('record_id')->all());
        $this->assertNotContains($confidential->id, collect($managerQueue['overdue_dispatches'])->pluck('record_id')->all());
        $this->assertNotContains($confidential->id, collect($managerQueue['unanswered_correspondence'])->pluck('record_id')->all());
    }

    public function test_dispatch_schedule_changes_are_audited_and_terminal_dispatch_cannot_be_rescheduled(): void
    {
        [$admin, , $office] = $this->officeFixture();
        $record = $this->formalRecord($office, $admin, 'Scheduled');
        $target = User::factory()->create();
        GroupUser::query()->create(['group_id' => $office->scope_id, 'user_id' => $target->id, 'role' => 0, 'status' => 1]);
        $service = app(SecretariatDispatchService::class);
        $dispatch = $service->create($record, $admin, [
            'dispatch_type' => 'referral', 'channel' => 'internal', 'target_user_id' => $target->id,
        ]);

        $service->schedule($dispatch, $admin, now()->addDay(), now()->addHours(2));
        $this->assertDatabaseHas('secretariat_audit_events', ['event_type' => 'dispatch_schedule_changed', 'record_id' => $record->id]);

        $service->transition($dispatch->fresh(), 'cancelled', $admin);
        $this->expectException(\Illuminate\Validation\ValidationException::class);
        $service->schedule($dispatch->fresh(), $admin, now()->addDays(2), null);
    }

    private function officeFixture(bool $withInspector = false): array
    {
        $admin = User::factory()->create(['is_admin' => 1]);
        $manager = User::factory()->create();
        $group = Group::query()->create(['name' => 'Queue Group', 'group_type' => '0']);
        GroupUser::query()->create(['group_id' => $group->id, 'user_id' => $manager->id, 'role' => 3, 'status' => 1]);
        $office = app(SecretariatOfficeService::class)->create([
            'code' => 'QUEUE-' . $group->id,
            'name' => 'Queue Office',
            'office_type' => 'group',
            'scope_type' => 'group',
            'scope_id' => $group->id,
        ]);

        if (! $withInspector) {
            return [$admin, $manager, $office];
        }

        $inspector = User::factory()->create();
        GroupUser::query()->create(['group_id' => $group->id, 'user_id' => $inspector->id, 'role' => 2, 'status' => 1]);
        return [$admin, $manager, $office, $inspector];
    }

    private function formalRecord($office, User $actor, string $title, string $confidentiality = 'office_members')
    {
        $service = app(SecretariatRecordService::class);
        $record = $service->createDraft($office, $actor, [
            'record_type' => 'official_note', 'direction' => 'internal', 'title' => $title,
            'body' => 'Body', 'confidentiality' => $confidentiality,
        ]);
        $service->submitForApproval($record, $actor);
        return $service->register($record->fresh(), $actor)->load('office');
    }
}
