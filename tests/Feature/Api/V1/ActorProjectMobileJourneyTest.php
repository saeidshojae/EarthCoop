<?php

namespace Tests\Feature\Api\V1;

use App\Models\Group;
use App\Models\GroupUser;
use App\Models\User;
use App\Modules\NajmBahar\Models\Project;
use App\Modules\NajmBahar\Models\ProjectCategory;
use App\Modules\NajmBahar\Services\AccountService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

final class ActorProjectMobileJourneyTest extends TestCase
{
    use RefreshDatabase;

    public function test_native_principal_can_use_group_actor_without_leaking_actor_state_into_personal_domains(): void
    {
        $principal = $this->member('principal');
        [$token, $deviceId] = $this->nativeSession($principal);

        $account = app(AccountService::class)->createMainAccountForUser((int) $principal->id, 'M4 principal');
        $this->bearer($token, $deviceId)
            ->getJson('/api/v1/najm-bahar/account')
            ->assertOk()
            ->assertJsonPath('data.id', (int) $account->id);

        $managed = $this->group('managed');
        $membership = $this->membership($principal, $managed, 3);
        $hidden = $this->group('not-representable');
        $this->membership($principal, $hidden, 1);

        $actors = $this->bearer($token, $deviceId)
            ->getJson('/api/v1/actors')
            ->assertOk()
            ->assertJsonPath('data.items.0.type', 'user')
            ->assertJsonPath('data.items.0.id', (string) $principal->id);

        $items = collect($actors->json('data.items'));
        $this->assertTrue($items->contains(fn (array $item): bool => ($item['type'] ?? null) === 'group' && ($item['id'] ?? null) === (string) $managed->id));
        $this->assertFalse($items->contains(fn (array $item): bool => ($item['type'] ?? null) === 'group' && ($item['id'] ?? null) === (string) $hidden->id));

        $category = ProjectCategory::factory()->create();
        $payload = $this->projectPayload($category->id, 'M4 managed group project');
        $payload['owner_actor'] = 'group:'.$managed->id;
        $key = 'm4-mobile-group-project-0001';

        $created = $this->bearer($token, $deviceId)
            ->withHeader('Idempotency-Key', $key)
            ->postJson('/api/v1/projects', $payload)
            ->assertCreated()
            ->assertJsonPath('data.owner_actor.type', 'group')
            ->assertJsonPath('data.owner_actor.id', (string) $managed->id);
        $groupProjectId = (int) $created->json('data.id');
        $this->assertStringNotContainsString(Group::class, $created->getContent());

        $replay = $this->bearer($token, $deviceId)
            ->withHeader('Idempotency-Key', $key)
            ->postJson('/api/v1/projects', $payload)
            ->assertCreated()
            ->assertHeader('Idempotency-Replayed', 'true');
        $this->assertSame($groupProjectId, (int) $replay->json('data.id'));
        $this->assertSame(1, Project::query()->where('owner_type', Group::class)->where('owner_id', $managed->id)->count());

        $this->bearer($token, $deviceId)
            ->getJson('/api/v1/projects')
            ->assertOk()
            ->assertJsonCount(0, 'data.items');

        $this->bearer($token, $deviceId)
            ->getJson('/api/v1/projects?'.http_build_query(['filter' => ['owner_actor' => 'group:'.$managed->id]]))
            ->assertOk()
            ->assertJsonPath('data.items.0.id', $groupProjectId)
            ->assertJsonPath('data.items.0.owner_actor.type', 'group');

        $update = $this->projectPayload($category->id, 'M4 updated group project');
        $this->bearer($token, $deviceId)
            ->withHeader('Idempotency-Key', 'm4-mobile-group-update-0001')
            ->putJson('/api/v1/projects/'.$groupProjectId, $update)
            ->assertOk()
            ->assertJsonPath('data.title', 'M4 updated group project');

        $outsider = $this->member('outsider');
        $this->membership($outsider, $managed, 1);
        [$outsiderToken, $outsiderDeviceId] = $this->nativeSession($outsider);
        $this->bearer($outsiderToken, $outsiderDeviceId)
            ->getJson('/api/v1/projects/'.$groupProjectId)
            ->assertForbidden();
        $this->bearer($outsiderToken, $outsiderDeviceId)
            ->withHeader('Idempotency-Key', 'm4-mobile-outsider-update-0001')
            ->putJson('/api/v1/projects/'.$groupProjectId, $update)
            ->assertForbidden();

        $membership->update(['role' => 1]);
        Auth::forgetGuards();
        $this->bearer($token, $deviceId)
            ->withHeader('Idempotency-Key', 'm4-mobile-downgraded-update-0001')
            ->putJson('/api/v1/projects/'.$groupProjectId, $update)
            ->assertForbidden();

        $afterDowngrade = $this->bearer($token, $deviceId)->getJson('/api/v1/actors')->assertOk();
        $this->assertFalse(collect($afterDowngrade->json('data.items'))->contains(
            fn (array $item): bool => ($item['type'] ?? null) === 'group' && ($item['id'] ?? null) === (string) $managed->id,
        ));

        $membership->update(['role' => 3]);
        Auth::forgetGuards();
        $this->bearer($token, $deviceId)
            ->withHeader('Idempotency-Key', 'm4-mobile-group-submit-0001')
            ->postJson('/api/v1/projects/'.$groupProjectId.'/submit')
            ->assertOk()
            ->assertJsonPath('data.status', 'pending');

        $membership->update(['role' => 1]);
        Auth::forgetGuards();

        $personal = $this->bearer($token, $deviceId)
            ->withHeader('Idempotency-Key', 'm4-mobile-personal-project-0001')
            ->postJson('/api/v1/projects', $this->projectPayload($category->id, 'M4 personal project'))
            ->assertCreated()
            ->assertJsonPath('data.owner_actor.type', 'user')
            ->assertJsonPath('data.owner_actor.id', (string) $principal->id);
        $personalProjectId = (int) $personal->json('data.id');

        $defaultList = $this->bearer($token, $deviceId)
            ->getJson('/api/v1/projects')
            ->assertOk()
            ->assertJsonCount(1, 'data.items')
            ->assertJsonPath('data.items.0.id', $personalProjectId)
            ->assertJsonPath('data.items.0.owner_actor.type', 'user');
        $this->assertStringNotContainsString(User::class, $defaultList->getContent());

        $this->bearer($token, $deviceId)
            ->getJson('/api/v1/najm-bahar/account')
            ->assertOk()
            ->assertJsonPath('data.id', (int) $account->id);
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
            'problem_statement' => 'M4 mobile actor project problem',
            'solution_description' => 'M4 mobile actor project solution',
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
            'summary' => 'M4 mobile actor project summary',
            'description' => 'M4 mobile actor project description',
        ];
    }

    private function member(string $suffix): User
    {
        return User::factory()->create([
            'email' => 'm4-mobile-'.$suffix.'-'.bin2hex(random_bytes(4)).'@example.test',
            'password' => Hash::make('secret-password'),
            'is_system' => false,
            'status' => 'active',
            'email_verified_at' => now(),
        ]);
    }

    private function group(string $suffix): Group
    {
        return Group::create([
            'name' => 'M4 mobile '.$suffix,
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
