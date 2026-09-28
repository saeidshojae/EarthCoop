<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Models\Group;
use App\Models\GroupUser;
use App\Models\Role;
use App\Models\User;
use App\Modules\NajmBahar\Models\Project;
use App\Modules\NajmBahar\Models\ProjectCategory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
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

        $this->bearer($token, $deviceId)
            ->getJson('/api/v1/projects?filter%5Bowner_actor%5D%5Btype%5D=group')
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'actor_reference_invalid');
    }

    public function test_create_defaults_to_self_and_accepts_explicit_self_and_authorized_group_owner(): void
    {
        $principal = $this->member('create');
        [$token, $deviceId] = $this->nativeSession($principal);
        $category = ProjectCategory::factory()->create();

        $implicit = $this->bearer($token, $deviceId)
            ->withHeader('Idempotency-Key', 'm4-create-self-implicit')
            ->postJson('/api/v1/projects', $this->projectPayload($category->id, 'Implicit self'))
            ->assertCreated()
            ->assertJsonPath('data.owner_actor.type', 'user')
            ->assertJsonPath('data.owner_actor.id', (string) $principal->id);

        $explicitPayload = $this->projectPayload($category->id, 'Explicit self');
        $explicitPayload['owner_actor'] = 'user:'.$principal->id;
        $this->bearer($token, $deviceId)
            ->withHeader('Idempotency-Key', 'm4-create-self-explicit')
            ->postJson('/api/v1/projects', $explicitPayload)
            ->assertCreated()
            ->assertJsonPath('data.owner_actor.type', 'user')
            ->assertJsonPath('data.owner_actor.id', (string) $principal->id);

        $group = $this->group('create-owner');
        $this->membership($principal, $group, 3);
        $groupPayload = $this->projectPayload($category->id, 'Group owner');
        $groupPayload['owner_actor'] = 'group:'.$group->id;
        $groupResponse = $this->bearer($token, $deviceId)
            ->withHeader('Idempotency-Key', 'm4-create-group-owner')
            ->postJson('/api/v1/projects', $groupPayload)
            ->assertCreated()
            ->assertJsonPath('data.owner_actor.type', 'group')
            ->assertJsonPath('data.owner_actor.id', (string) $group->id);

        $groupProjectId = (int) $groupResponse->json('data.id');
        $this->assertDatabaseHas('najm_bahar_projects', [
            'id' => $groupProjectId,
            'owner_type' => Group::class,
            'owner_id' => $group->id,
        ]);
        $this->assertDatabaseHas('api_v1_idempotency_keys', [
            'actor_key' => 'user:'.$principal->id,
            'scope' => 'api.v1.projects.store',
            'idempotency_key' => 'm4-create-group-owner',
            'state' => 'completed',
        ]);

        $this->assertNotSame((int) $implicit->json('data.id'), $groupProjectId);
    }

    public function test_create_denies_every_unrepresentable_actor_even_for_site_admin(): void
    {
        $principal = $this->member('create-denied');
        $other = $this->member('create-other');
        [$token, $deviceId] = $this->nativeSession($principal);
        $category = ProjectCategory::factory()->create();
        $admin = Role::query()->firstOrCreate(
            ['slug' => 'admin'],
            ['name' => 'Admin', 'description' => 'Admin', 'is_system' => true, 'order' => 0],
        );
        $principal->assignRole($admin);

        foreach ([0, 1, 2, 4, 5] as $role) {
            $group = $this->group('denied-role-'.$role);
            $this->membership($principal, $group, $role);
            $payload = $this->projectPayload($category->id, 'Denied role '.$role);
            $payload['owner_actor'] = 'group:'.$group->id;

            $this->bearer($token, $deviceId)
                ->withHeader('Idempotency-Key', 'm4-denied-role-'.$role)
                ->postJson('/api/v1/projects', $payload)
                ->assertForbidden()
                ->assertJsonPath('error.code', 'actor_representation_forbidden');
        }

        $nonMember = $this->group('denied-nonmember');
        $payload = $this->projectPayload($category->id, 'Denied nonmember');
        $payload['owner_actor'] = 'group:'.$nonMember->id;
        $this->bearer($token, $deviceId)
            ->withHeader('Idempotency-Key', 'm4-denied-nonmember')
            ->postJson('/api/v1/projects', $payload)
            ->assertForbidden()
            ->assertJsonPath('error.code', 'actor_representation_forbidden');

        $payload = $this->projectPayload($category->id, 'Denied other user');
        $payload['owner_actor'] = 'user:'.$other->id;
        $this->bearer($token, $deviceId)
            ->withHeader('Idempotency-Key', 'm4-denied-other-user')
            ->postJson('/api/v1/projects', $payload)
            ->assertForbidden()
            ->assertJsonPath('error.code', 'actor_representation_forbidden');

        foreach ([
            ['organization:reserved', 422, 'actor_not_supported', 'm4-denied-organization'],
            ['system:earthcoop', 403, 'actor_representation_forbidden', 'm4-denied-system'],
        ] as [$actor, $status, $code, $key]) {
            $payload = $this->projectPayload($category->id, 'Denied reserved actor');
            $payload['owner_actor'] = $actor;
            $this->bearer($token, $deviceId)
                ->withHeader('Idempotency-Key', $key)
                ->postJson('/api/v1/projects', $payload)
                ->assertStatus($status)
                ->assertJsonPath('error.code', $code);
        }
    }

    public function test_group_manager_can_view_update_and_submit_then_loses_access_on_downgrade(): void
    {
        $principal = $this->member('policy');
        [$token, $deviceId] = $this->nativeSession($principal);
        $group = $this->group('policy-owner');
        $membership = $this->membership($principal, $group, 3);
        $category = ProjectCategory::factory()->create();
        $project = Project::factory()->create([
            'owner_type' => Group::class,
            'owner_id' => $group->id,
            'category_level1_id' => $category->id,
            'status' => 'draft',
            'project_visibility' => 'private',
        ]);

        $this->bearer($token, $deviceId)
            ->getJson('/api/v1/projects/'.$project->id)
            ->assertOk()
            ->assertJsonPath('data.owner_actor.type', 'group');

        $this->bearer($token, $deviceId)
            ->withHeader('Idempotency-Key', 'm4-group-update')
            ->putJson('/api/v1/projects/'.$project->id, $this->projectPayload($category->id, 'Updated group project'))
            ->assertOk()
            ->assertJsonPath('data.title', 'Updated group project');

        $this->bearer($token, $deviceId)
            ->withHeader('Idempotency-Key', 'm4-group-submit')
            ->postJson('/api/v1/projects/'.$project->id.'/submit')
            ->assertOk()
            ->assertJsonPath('data.status', 'pending');

        $membership->update(['role' => 1]);
        Auth::forgetGuards();

        $this->bearer($token, $deviceId)
            ->getJson('/api/v1/projects/'.$project->id)
            ->assertForbidden();
    }

    public function test_public_visibility_and_delete_status_rules_remain_unchanged_for_group_owners(): void
    {
        $manager = $this->member('delete-manager');
        $outsider = $this->member('public-outsider');
        [$outsiderToken, $outsiderDevice] = $this->nativeSession($outsider);
        $group = $this->group('delete-owner');
        $this->membership($manager, $group, 3);

        $public = Project::factory()->approved()->create([
            'owner_type' => Group::class,
            'owner_id' => $group->id,
            'project_visibility' => 'public',
        ]);
        $draft = Project::factory()->create([
            'owner_type' => Group::class,
            'owner_id' => $group->id,
            'status' => 'draft',
            'project_visibility' => 'private',
        ]);

        $this->bearer($outsiderToken, $outsiderDevice)
            ->getJson('/api/v1/projects/'.$public->id)
            ->assertOk();

        $this->assertTrue(Gate::forUser($manager)->allows('delete', $draft));
        $this->assertFalse(Gate::forUser($manager)->allows('delete', $public));
    }

    public function test_update_cannot_change_owner_actor(): void
    {
        $principal = $this->member('immutable-owner');
        [$token, $deviceId] = $this->nativeSession($principal);
        $group = $this->group('immutable-owner');
        $this->membership($principal, $group, 3);
        $category = ProjectCategory::factory()->create();
        $project = Project::factory()->create([
            'owner_type' => Group::class,
            'owner_id' => $group->id,
            'category_level1_id' => $category->id,
            'status' => 'draft',
        ]);

        $payload = $this->projectPayload($category->id, 'Attempt owner switch');
        $payload['owner_actor'] = 'user:'.$principal->id;

        $this->bearer($token, $deviceId)
            ->withHeader('Idempotency-Key', 'm4-owner-immutable')
            ->putJson('/api/v1/projects/'.$project->id, $payload)
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'validation_failed');

        $project->refresh();
        $this->assertSame(Group::class, $project->owner_type);
        $this->assertSame($group->id, (int) $project->owner_id);
    }

    public function test_idempotency_replays_same_group_owner_and_rejects_actor_switch_without_second_effect(): void
    {
        $principal = $this->member('idempotency');
        [$token, $deviceId] = $this->nativeSession($principal);
        $category = ProjectCategory::factory()->create();
        $groupA = $this->group('idem-a');
        $groupB = $this->group('idem-b');
        $this->membership($principal, $groupA, 3);
        $this->membership($principal, $groupB, 3);
        $key = 'm4-group-idempotency';

        $payloadA = $this->projectPayload($category->id, 'Idempotent group project');
        $payloadA['owner_actor'] = 'group:'.$groupA->id;

        $first = $this->bearer($token, $deviceId)
            ->withHeader('Idempotency-Key', $key)
            ->postJson('/api/v1/projects', $payloadA)
            ->assertCreated();
        $projectId = (int) $first->json('data.id');

        $replay = $this->bearer($token, $deviceId)
            ->withHeader('Idempotency-Key', $key)
            ->postJson('/api/v1/projects', $payloadA)
            ->assertCreated()
            ->assertHeader('Idempotency-Replayed', 'true');
        $this->assertSame($projectId, (int) $replay->json('data.id'));
        $this->assertSame(1, Project::query()->where('owner_type', Group::class)->where('owner_id', $groupA->id)->count());

        $payloadB = $payloadA;
        $payloadB['owner_actor'] = 'group:'.$groupB->id;
        $this->bearer($token, $deviceId)
            ->withHeader('Idempotency-Key', $key)
            ->postJson('/api/v1/projects', $payloadB)
            ->assertStatus(409)
            ->assertJsonPath('error.code', 'idempotency_key_reused');

        $this->assertSame(0, Project::query()->where('owner_type', Group::class)->where('owner_id', $groupB->id)->count());
        $this->assertSame('user:'.$principal->id, (string) DB::table('api_v1_idempotency_keys')->where('idempotency_key', $key)->value('actor_key'));
    }

    private function projectsFor(string $actor): string
    {
        return '/api/v1/projects?'.http_build_query([
            'filter' => ['owner_actor' => $actor],
        ]);
    }

    private function projectPayload(int $categoryId, string $title): array
    {
        return [
            'title' => $title,
            'project_type' => 'production',
            'project_visibility' => 'private',
            'project_stage' => 'documented',
            'investment_method' => 'auction_shares',
            'category_level1_id' => $categoryId,
            'problem_statement' => 'M4 actor project problem',
            'solution_description' => 'M4 actor project solution',
            'target_market' => 'general',
            'base_value_min' => 1000000,
            'base_value_max' => 5000000,
            'total_shares' => 100,
            'initial_auction_percent' => 30,
            'max_user_ownership_percent' => 20,
            'auction_period' => 'monthly',
            'risk_level' => 'medium',
            'oversight_type' => 'guild',
            'reporting_interval' => 'monthly',
            'fund_usage_scope' => 'project_only',
            'accept_transparency' => true,
            'failure_policy' => 'refund',
            'value_update_trigger' => 'stage_progress',
            'accept_rules' => true,
            'summary' => 'M4 actor project summary',
            'description' => 'M4 actor project description',
        ];
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
