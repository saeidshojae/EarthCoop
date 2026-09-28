<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Models\Group;
use App\Models\GroupUser;
use App\Models\User;
use App\Modules\NajmBahar\Models\Project;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

final class ProjectActorContractTest extends TestCase
{
    use RefreshDatabase;

    public function test_project_reads_serialize_owner_actor_and_default_list_stays_personal(): void
    {
        $principal = $this->member('personal-default');
        [$token, $deviceId] = $this->nativeSession($principal);
        $group = $this->group('managed');
        $this->membership($principal, $group, 3);

        $personal = Project::factory()->create([
            'owner_type' => User::class,
            'owner_id' => $principal->id,
            'project_visibility' => 'private',
        ]);
        Project::factory()->create([
            'owner_type' => Group::class,
            'owner_id' => $group->id,
            'project_visibility' => 'private',
        ]);

        $response = $this->bearer($token, $deviceId)
            ->getJson('/api/v1/projects')
            ->assertOk()
            ->assertJsonPath('data.items.0.id', $personal->id)
            ->assertJsonPath('data.items.0.owner_actor.type', 'user')
            ->assertJsonPath('data.items.0.owner_actor.id', (string) $principal->id);

        $this->assertCount(1, $response->json('data.items'));
        $this->assertStringNotContainsString(User::class, $response->getContent());
        $this->assertStringNotContainsString(Group::class, $response->getContent());

        $this->bearer($token, $deviceId)
            ->getJson('/api/v1/projects/'.$personal->id)
            ->assertOk()
            ->assertJsonPath('data.owner_actor.type', 'user')
            ->assertJsonPath('data.owner_actor.id', (string) $principal->id);
    }

    public function test_authorized_group_filter_is_live_and_manager_downgrade_revokes_it(): void
    {
        $principal = $this->member('group-filter');
        [$token, $deviceId] = $this->nativeSession($principal);
        $group = $this->group('group-filter');
        $membership = $this->membership($principal, $group, 3);
        $project = Project::factory()->create([
            'owner_type' => Group::class,
            'owner_id' => $group->id,
            'project_visibility' => 'private',
        ]);

        $url = $this->projectsFor('group:'.$group->id);
        $this->bearer($token, $deviceId)
            ->getJson($url)
            ->assertOk()
            ->assertJsonPath('data.items.0.id', $project->id)
            ->assertJsonPath('data.items.0.owner_actor.type', 'group')
            ->assertJsonPath('data.items.0.owner_actor.id', (string) $group->id);

        $membership->update(['role' => 1]);
        Auth::forgetGuards();

        $this->bearer($token, $deviceId)
            ->getJson($url)
            ->assertForbidden()
            ->assertJsonPath('error.code', 'actor_representation_forbidden');
    }

    public function test_actor_filter_denies_other_user_non_manager_unsupported_and_deleted_owners(): void
    {
        $principal = $this->member('denials');
        $other = $this->member('other');
        [$token, $deviceId] = $this->nativeSession($principal);

        $this->bearer($token, $deviceId)
            ->getJson($this->projectsFor('user:'.$other->id))
            ->assertForbidden()
            ->assertJsonPath('error.code', 'actor_representation_forbidden');

        $nonManaged = $this->group('non-manager');
        $this->membership($principal, $nonManaged, 1);
        $this->bearer($token, $deviceId)
            ->getJson($this->projectsFor('group:'.$nonManaged->id))
            ->assertForbidden()
            ->assertJsonPath('error.code', 'actor_representation_forbidden');

        $this->bearer($token, $deviceId)
            ->getJson($this->projectsFor('organization:reserved'))
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'actor_not_supported');

        $this->bearer($token, $deviceId)
            ->getJson($this->projectsFor('system:earthcoop'))
            ->assertForbidden()
            ->assertJsonPath('error.code', 'actor_representation_forbidden');

        $deleted = $this->group('deleted');
        $deletedId = $deleted->id;
        $deleted->delete();
        $this->bearer($token, $deviceId)
            ->getJson($this->projectsFor('group:'.$deletedId))
            ->assertNotFound()
            ->assertJsonPath('error.code', 'actor_not_found');
    }

    public function test_hostile_owner_actor_filters_fail_closed(): void
    {
        $principal = $this->member('hostile');
        [$token, $deviceId] = $this->nativeSession($principal);

        foreach ([
            'group',
            'group:1:2',
            'unknown:123',
            'group:App\\Models\\Group',
            'group:../1',
            'group:1/2',
            'group:1\\2',
        ] as $value) {
            $this->bearer($token, $deviceId)
                ->getJson($this->projectsFor($value))
                ->assertStatus(422)
                ->assertJsonPath('error.code', 'actor_reference_invalid');
        }
    }

    private function projectsFor(string $actor): string
    {
        return '/api/v1/projects?'.http_build_query([
            'filter' => ['owner_actor' => $actor],
        ]);
    }

    private function member(string $suffix): User
    {
        return User::factory()->create([
            'email' => 'm4-project-'.$suffix.'-'.bin2hex(random_bytes(4)).'@example.test',
            'password' => Hash::make('secret-password'),
            'is_system' => false,
            'status' => 'active',
            'email_verified_at' => now(),
        ]);
    }

    private function group(string $suffix): Group
    {
        return Group::create([
            'name' => 'M4 project '.$suffix,
            'group_type' => 0,
            'is_open' => 1,
        ]);
    }

    private function membership(User $user, Group $group, int $role): GroupUser
    {
        return GroupUser::create([
            'group_id' => $group->id,
            'user_id' => $user->id,
            'role' => $role,
            'status' => 1,
        ]);
    }

    private function nativeSession(User $user): array
    {
        $response = $this->postJson('/api/v1/auth/session', [
            'email' => $user->email,
            'password' => 'secret-password',
            'platform' => 'android',
            'app_version' => '1.0.0',
            'locale' => 'fa',
            'timezone' => 'Asia/Tehran',
            'push_capable' => true,
        ])->assertCreated();

        return [(string) $response->json('data.token'), (string) $response->json('data.device.id')];
    }

    private function bearer(string $token, string $deviceId): self
    {
        Auth::forgetGuards();

        return $this->withToken($token)->withHeader('X-Device-ID', $deviceId);
    }
}
