<?php

namespace Tests\Feature\Api\V1;

use App\Models\Group;
use App\Models\GroupUser;
use App\Models\User;
use App\Modules\NajmBahar\Models\Project;
use App\Modules\NajmBahar\Models\ProjectCategory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class OfflineReplayContractTest extends TestCase
{
    use RefreshDatabase;

    public function test_exact_replay_is_single_effect_while_current_authority_is_still_valid(): void
    {
        [$user, $token, $deviceId, $membership, $project, $category] = $this->groupProjectContext('valid');
        $key = 'm5-offline-valid-replay';
        $payload = $this->projectPayload($category->id, 'Offline replay title');

        $first = $this->bearer($token, $deviceId)
            ->withHeader('Idempotency-Key', $key)
            ->putJson('/api/v1/projects/'.$project->id, $payload)
            ->assertOk()
            ->assertJsonPath('data.title', 'Offline replay title');

        $updatedAt = Project::query()->findOrFail($project->id)->updated_at?->toISOString();

        $second = $this->bearer($token, $deviceId)
            ->withHeader('Idempotency-Key', $key)
            ->putJson('/api/v1/projects/'.$project->id, $payload)
            ->assertOk()
            ->assertHeader('Idempotency-Replayed', 'true')
            ->assertJsonPath('data.id', $first->json('data.id'));

        $this->assertSame($updatedAt, Project::query()->findOrFail($project->id)->updated_at?->toISOString());
    }

    public function test_replay_rechecks_live_group_authority_before_returning_cached_success(): void
    {
        [$user, $token, $deviceId, $membership, $project, $category] = $this->groupProjectContext('revoked');
        $key = 'm5-offline-authority-change';
        $payload = $this->projectPayload($category->id, 'Authorized once');

        $this->bearer($token, $deviceId)
            ->withHeader('Idempotency-Key', $key)
            ->putJson('/api/v1/projects/'.$project->id, $payload)
            ->assertOk();

        $membership->update(['role' => 1]);
        Auth::forgetGuards();

        $this->bearer($token, $deviceId)
            ->withHeader('Idempotency-Key', $key)
            ->putJson('/api/v1/projects/'.$project->id, $payload)
            ->assertForbidden()
            ->assertHeaderMissing('Idempotency-Replayed');
    }

    private function groupProjectContext(string $suffix): array
    {
        $user = User::factory()->create([
            'email' => 'm5-offline-'.$suffix.'-'.bin2hex(random_bytes(4)).'@example.test',
            'password' => Hash::make('secret-password'),
            'is_system' => false,
            'status' => 'active',
            'email_verified_at' => now(),
        ]);
        [$token, $deviceId] = $this->nativeSession($user);
        $group = Group::factory()->create();
        $membership = GroupUser::create([
            'group_id' => $group->id,
            'user_id' => $user->id,
            'role' => 3,
            'status' => 1,
        ]);
        $category = ProjectCategory::factory()->create();
        $project = Project::factory()->create([
            'owner_type' => Group::class,
            'owner_id' => $group->id,
            'category_level1_id' => $category->id,
            'status' => 'draft',
            'project_visibility' => 'private',
        ]);

        return [$user, $token, $deviceId, $membership, $project, $category];
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
            'problem_statement' => 'Problem statement',
            'solution_description' => 'Solution description',
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
        ];
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

        return [$response->json('data.token'), $response->json('data.device.id')];
    }

    private function bearer(string $token, string $deviceId): self
    {
        Auth::forgetGuards();

        return $this->withToken($token)->withHeader('X-Device-ID', $deviceId);
    }
}
