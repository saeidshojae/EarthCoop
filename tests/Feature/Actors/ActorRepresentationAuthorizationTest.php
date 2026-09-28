<?php

declare(strict_types=1);

namespace Tests\Feature\Actors;

use App\Models\Group;
use App\Models\GroupUser;
use App\Models\User;
use App\Services\Actors\ActorBoundaryException;
use App\Services\Actors\ActorOperation;
use App\Services\Actors\ActorReference;
use App\Services\Actors\ActorRepresentationAuthorizationService;
use App\Services\Actors\ActorType;
use App\Services\Actors\OwnerRepresentationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class ActorRepresentationAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_representation_is_self_only_and_system_users_are_denied(): void
    {
        $principal = User::factory()->create(['is_system' => false]);
        $other = User::factory()->create(['is_system' => false]);
        $system = User::factory()->create(['is_system' => true]);
        $service = app(ActorRepresentationAuthorizationService::class);

        $this->assertTrue($service->allows($principal, new ActorReference(ActorType::User, (string) $principal->id), ActorOperation::ProjectOwner));
        $this->assertFalse($service->allows($principal, new ActorReference(ActorType::User, (string) $other->id), ActorOperation::ProjectOwner));
        $this->assertFalse($service->allows($system, new ActorReference(ActorType::User, (string) $system->id), ActorOperation::ProjectOwner));
    }

    public function test_only_current_active_manager_role_can_represent_group_for_project_owner_operation(): void
    {
        $principal = User::factory()->create(['is_system' => false]);
        $service = app(ActorRepresentationAuthorizationService::class);

        foreach ([0, 1, 2, 3, 4, 5] as $role) {
            $group = $this->group('role-'.$role);
            GroupUser::create([
                'group_id' => $group->id,
                'user_id' => $principal->id,
                'role' => $role,
                'status' => 1,
                'expired' => null,
            ]);

            $allowed = $service->allows(
                $principal,
                new ActorReference(ActorType::Group, (string) $group->id),
                ActorOperation::ProjectOwner,
            );

            $this->assertSame($role === 3, $allowed, 'Only role 3 may represent a Group owner.');
        }
    }

    public function test_inactive_expired_and_admin_only_membership_do_not_create_group_representation(): void
    {
        $principal = User::factory()->create(['is_system' => false, 'is_admin' => true]);
        $service = app(ActorRepresentationAuthorizationService::class);

        $inactive = $this->group('inactive');
        GroupUser::create([
            'group_id' => $inactive->id,
            'user_id' => $principal->id,
            'role' => 3,
            'status' => 0,
        ]);

        $expired = $this->group('expired');
        GroupUser::create([
            'group_id' => $expired->id,
            'user_id' => $principal->id,
            'role' => 3,
            'status' => 1,
            'expired' => now()->subMinute(),
        ]);

        $unrelated = $this->group('admin-only');

        foreach ([$inactive, $expired, $unrelated] as $group) {
            $this->assertFalse($service->allows(
                $principal,
                new ActorReference(ActorType::Group, (string) $group->id),
                ActorOperation::ProjectOwner,
            ));
        }
    }

    public function test_expired_temporary_manager_override_is_restored_before_authorization(): void
    {
        $principal = User::factory()->create(['is_system' => false]);
        $group = $this->group('temporary-expired');
        $membership = GroupUser::create([
            'group_id' => $group->id,
            'user_id' => $principal->id,
            'role' => 3,
            'status' => 1,
            'role_override_active' => true,
            'role_override_original_role' => 1,
            'role_override_started_at' => now()->subHours(2),
            'role_override_expires_at' => now()->subMinute(),
            'role_override_changed_by' => $principal->id,
            'role_override_source' => 'm4-test',
        ]);

        $this->assertFalse(app(ActorRepresentationAuthorizationService::class)->allows(
            $principal,
            new ActorReference(ActorType::Group, (string) $group->id),
            ActorOperation::ProjectOwner,
        ));

        $membership->refresh();
        $this->assertSame(1, (int) $membership->role);
        $this->assertFalse((bool) $membership->role_override_active);
    }

    public function test_live_authority_is_rechecked_after_manager_downgrade_without_session_change(): void
    {
        $principal = User::factory()->create(['is_system' => false]);
        $group = $this->group('stale-authority');
        $membership = GroupUser::create([
            'group_id' => $group->id,
            'user_id' => $principal->id,
            'role' => 3,
            'status' => 1,
        ]);
        $service = app(ActorRepresentationAuthorizationService::class);
        $actor = new ActorReference(ActorType::Group, (string) $group->id);

        $this->assertTrue($service->allows($principal, $actor, ActorOperation::ProjectOwner));

        $membership->update(['role' => 1]);

        $this->assertFalse($service->allows($principal, $actor, ActorOperation::ProjectOwner));
    }

    public function test_reserved_and_system_actors_fail_with_stable_representation_semantics(): void
    {
        $principal = User::factory()->create(['is_system' => false]);
        $service = app(ActorRepresentationAuthorizationService::class);

        try {
            $service->authorize($principal, new ActorReference(ActorType::Organization, 'org-1'), ActorOperation::ProjectOwner);
            $this->fail('Organization representation must remain unsupported in M4.');
        } catch (ActorBoundaryException $exception) {
            $this->assertSame('actor_not_supported', $exception->errorCode());
        }

        try {
            $service->authorize($principal, new ActorReference(ActorType::System, 'earthcoop'), ActorOperation::ProjectOwner);
            $this->fail('Public principals must never represent the System actor.');
        } catch (ActorBoundaryException $exception) {
            $this->assertSame('actor_representation_forbidden', $exception->errorCode());
        }
    }

    public function test_owner_representation_fails_closed_for_unknown_or_stale_legacy_owner(): void
    {
        $principal = User::factory()->create(['is_system' => false]);
        $service = app(OwnerRepresentationService::class);

        $this->assertFalse($service->allows($principal, 'App\\Models\\UnknownOwner', 1, ActorOperation::ProjectOwner));
        $this->assertFalse($service->allows($principal, Group::class, 999999, ActorOperation::ProjectOwner));
    }

    private function group(string $suffix): Group
    {
        return Group::create([
            'name' => 'M4 '.$suffix,
            'group_type' => '0',
            'is_open' => true,
        ]);
    }
}
