<?php

declare(strict_types=1);

namespace Tests\Feature\NajmBahar;

use App\Models\Group;
use App\Models\GroupUser;
use App\Models\User;
use App\Modules\NajmBahar\Models\Project;
use App\Modules\NajmBahar\Services\ProjectService;
use App\Notifications\NajmBahar\ProjectRevisionRequested;
use App\Notifications\NajmBahar\ProjectStatusChanged;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

final class ProjectOwnerNotificationTest extends TestCase
{
    use RefreshDatabase;

    private ProjectService $projects;
    private User $reviewer;

    protected function setUp(): void
    {
        parent::setUp();

        Notification::fake();
        $this->projects = app(ProjectService::class);
        $this->reviewer = $this->user('reviewer');
    }

    public function test_user_owner_notification_behavior_is_unchanged(): void
    {
        $owner = $this->user('personal-owner');
        $project = Project::factory()->pending()->create([
            'owner_type' => User::class,
            'owner_id' => $owner->id,
        ]);

        $this->projects->approveProject($project, $this->reviewer, 'approved');

        Notification::assertSentTo(
            $owner,
            ProjectStatusChanged::class,
            fn (ProjectStatusChanged $notification): bool =>
                $notification->project->id === $project->id && $notification->newStatus === 'approved',
        );
    }

    public function test_group_owner_lifecycle_routes_notifications_only_to_current_effective_managers(): void
    {
        $group = $this->group('owner');
        $managerA = $this->user('manager-a');
        $managerB = $this->user('manager-b');
        $this->membership($managerA, $group, 3);
        $this->membership($managerB, $group, 3);

        $excluded = [];
        foreach ([0, 1, 2, 4, 5] as $role) {
            $member = $this->user('role-'.$role);
            $this->membership($member, $group, $role);
            $excluded[] = $member;
        }

        $inactiveManager = $this->user('inactive-manager');
        $this->membership($inactiveManager, $group, 3, 0);
        $excluded[] = $inactiveManager;

        $expiredManager = $this->user('expired-manager');
        $this->membership($expiredManager, $group, 3, 1, now()->subMinute());
        $excluded[] = $expiredManager;

        $expiredTemporaryManager = $this->user('expired-temporary-manager');
        $temporary = $this->membership($expiredTemporaryManager, $group, 3);
        $temporary->forceFill([
            'role_override_active' => true,
            'role_override_original_role' => 1,
            'role_override_started_at' => now()->subHour(),
            'role_override_expires_at' => now()->subMinute(),
            'role_override_changed_by' => $this->reviewer->id,
            'role_override_source' => 'm4-test',
        ])->save();
        $excluded[] = $expiredTemporaryManager;

        $systemManager = $this->user('system-manager', true);
        $this->membership($systemManager, $group, 3);
        $excluded[] = $systemManager;

        $approved = Project::factory()->pending()->create([
            'owner_type' => Group::class,
            'owner_id' => $group->id,
        ]);
        $rejected = Project::factory()->pending()->create([
            'owner_type' => Group::class,
            'owner_id' => $group->id,
        ]);
        $revision = Project::factory()->pending()->create([
            'owner_type' => Group::class,
            'owner_id' => $group->id,
        ]);

        $this->projects->approveProject($approved, $this->reviewer, 'approved');
        $this->projects->rejectProject($rejected, $this->reviewer, 'rejected', 'rejected');
        $this->projects->requestRevision($revision, $this->reviewer, 'revise');

        foreach ([$managerA, $managerB] as $manager) {
            Notification::assertSentTo(
                $manager,
                ProjectStatusChanged::class,
                fn (ProjectStatusChanged $notification): bool =>
                    $notification->project->id === $approved->id && $notification->newStatus === 'approved',
            );
            Notification::assertSentTo(
                $manager,
                ProjectStatusChanged::class,
                fn (ProjectStatusChanged $notification): bool =>
                    $notification->project->id === $rejected->id && $notification->newStatus === 'rejected',
            );
            Notification::assertSentTo(
                $manager,
                ProjectRevisionRequested::class,
                fn (ProjectRevisionRequested $notification): bool => $notification->project->id === $revision->id,
            );
        }

        foreach ($excluded as $member) {
            Notification::assertNotSentTo($member, ProjectStatusChanged::class);
            Notification::assertNotSentTo($member, ProjectRevisionRequested::class);
        }

        $temporary->refresh();
        $this->assertSame(1, (int) $temporary->role);
        $this->assertFalse((bool) $temporary->role_override_active);
    }

    private function user(string $suffix, bool $system = false): User
    {
        return User::factory()->create([
            'email' => 'm4-owner-notification-'.$suffix.'-'.bin2hex(random_bytes(4)).'@example.test',
            'is_system' => $system,
            'status' => 'active',
            'email_verified_at' => now(),
        ]);
    }

    private function group(string $suffix): Group
    {
        return Group::create([
            'name' => 'M4 notification '.$suffix,
            'group_type' => 0,
            'is_open' => 1,
        ]);
    }

    private function membership(
        User $user,
        Group $group,
        int $role,
        int $status = 1,
        $expired = null,
    ): GroupUser {
        return GroupUser::create([
            'group_id' => $group->id,
            'user_id' => $user->id,
            'role' => $role,
            'status' => $status,
            'expired' => $expired,
        ]);
    }
}
