<?php

namespace Tests\Feature\Api\V1;

use App\Enums\Elections\ElectionLifecycleStatus;
use App\Models\Election;
use App\Models\ElectionEligibilitySnapshot;
use App\Models\GovernanceArea;
use App\Models\Group;
use App\Models\GroupSetting;
use App\Models\GroupUser;
use App\Models\User;
use App\Notifications\GenericNotification;
use App\Modules\NajmBahar\Models\ProjectCategory;
use App\Services\GroupChat\GroupFeedService;
use App\Services\LocationGovernance\ResidenceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\Group as TestGroup;
use Tests\Support\LocationGovernance\LocationFixture;
use Tests\TestCase;

#[TestGroup('mysql-core-journey')]
class CoreJourneyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'location-governance.runtime_enabled' => true,
            'location-governance.registration_enabled' => true,
            'location-governance.groups_enabled' => false,
            'location-governance.projects_enabled' => true,
            'location-governance.elections_enabled' => true,
            'group-chat.features.feed_sequence_v1' => true,
            'group-chat.features.feed_unread_v1' => true,
            'group-chat.features.delta_sync_v1' => true,
            'group-chat.features.transactional_outbox_v1' => false,
            'queue.default' => 'sync',
            'broadcasting.default' => 'null',
        ]);
    }

    public function test_native_bearer_client_completes_m1_core_journey_without_browser_session_or_csrf(): void
    {
        $user = User::factory()->create([
            'email' => 'm1-core-'.bin2hex(random_bytes(4)).'@example.test',
            'password' => Hash::make('secret-password'),
            'is_system' => false,
            'status' => 'active',
            'email_verified_at' => now(),
        ]);
        $other = User::factory()->create(['is_system' => false, 'status' => 'active']);

        $schema = LocationFixture::iranSchema();
        $path = LocationFixture::createPath($schema, ['country', 'province', 'county', 'section', 'city', 'urban_region', 'neighborhood']);
        $city = $path->firstWhere('level', 'city');
        $neighborhood = $path->firstWhere('level', 'neighborhood');
        $this->assertNotNull($city);
        $this->assertNotNull($neighborhood);

        $area = GovernanceArea::factory()->official()->create([
            'governance_type' => 'city',
            'status' => 'active',
            'canonical_name' => 'M1 Core City',
        ]);
        $area->locations()->attach($city->id);
        app(ResidenceService::class)->setInitialPrimaryResidence($user, $neighborhood, ['source' => 'm1_core_journey']);

        $group = Group::create([
            'name' => 'M1 Core Group',
            'group_type' => 0,
            'location_level' => 'city',
            'is_open' => 1,
            'governance_area_id' => $area->id,
            'dimension_key' => 'public',
            'dimension_value_key' => 'public',
        ]);
        GroupUser::create(['group_id' => $group->id, 'user_id' => $user->id, 'role' => 3, 'status' => 1]);
        GroupUser::create(['group_id' => $group->id, 'user_id' => $other->id, 'role' => 1, 'status' => 1]);
        app(GroupFeedService::class)->record($group->id, 'post', 99001, $other->id);

        GroupSetting::create([
            'level' => 'city',
            'manager_count' => 7,
            'inspector_count' => 3,
            'election_time' => 30,
            'second_election_time' => 6,
            'max_for_election' => 1,
            'election_status' => 1,
        ]);
        $election = Election::create([
            'group_id' => $group->id,
            'governance_area_id' => $area->id,
            'cycle_number' => 1,
            'starts_at' => now()->subMinute(),
            'ends_at' => now()->addDays(30),
            'is_closed' => false,
            'lifecycle_status' => ElectionLifecycleStatus::Open,
            'eligibility_snapshot_captured_at' => now(),
            'eligibility_snapshot_version' => 1,
        ]);
        ElectionEligibilitySnapshot::create([
            'election_id' => $election->id,
            'user_id' => $user->id,
            'voter_eligible' => true,
            'selectable_eligible' => true,
            'membership_role' => 3,
            'membership_status' => 1,
            'snapshot_version' => 1,
            'captured_at' => now(),
        ]);

        $category = ProjectCategory::create([
            'name' => 'M1 Core Category',
            'level' => 1,
            'parent_id' => null,
            'status' => true,
        ]);
        $user->notify(new GenericNotification('M1', 'Core journey notification', '/home', 'group.post'));
        $notification = $user->notifications()->latest()->firstOrFail();

        $login = $this->postJson('/api/v1/auth/session', [
            'email' => $user->email,
            'password' => 'secret-password',
            'platform' => 'android',
            'app_version' => '1.0.0',
            'locale' => 'fa',
            'timezone' => 'Asia/Tehran',
            'push_capable' => true,
        ])->assertCreated();
        $login->assertHeaderMissing('Set-Cookie');

        $token = (string) $login->json('data.token');
        $deviceId = (string) $login->json('data.device.id');

        $this->bearer($token, $deviceId)->getJson('/api/v1/me')
            ->assertOk()->assertJsonPath('data.id', $user->id);

        $this->bearer($token, $deviceId)->getJson('/api/v1/location/options/root')
            ->assertOk()->assertJsonStructure(['data']);
        $this->bearer($token, $deviceId)->getJson('/api/v1/location-governance/me')
            ->assertOk();

        $this->bearer($token, $deviceId)->getJson('/api/v1/groups')
            ->assertOk()->assertJsonPath('data.0.id', $group->id);
        $this->bearer($token, $deviceId)->getJson('/api/v1/groups/'.$group->id.'/feed/delta?after_sequence=0&limit=20')
            ->assertOk()->assertJsonPath('data.events.0.sequence', 1);
        $this->bearer($token, $deviceId)->getJson('/api/v1/groups/'.$group->id.'/unread')
            ->assertOk()->assertJsonPath('data.total', 1);

        $this->bearer($token, $deviceId)->getJson('/api/v1/groups/'.$group->id.'/elections/current')
            ->assertOk()
            ->assertJsonPath('data.election.id', $election->id)
            ->assertJsonPath('data.permissions.can_vote', true);

        $project = $this->bearer($token, $deviceId)
            ->withHeader('Idempotency-Key', 'm1-project-'.bin2hex(random_bytes(8)))
            ->postJson('/api/v1/projects', $this->projectPayload($category->id, $city->id))
            ->assertCreated()
            ->assertJsonPath('data.governance_area_id', $area->id);
        $projectId = (int) $project->json('data.id');
        $this->bearer($token, $deviceId)->getJson('/api/v1/projects/'.$projectId)
            ->assertOk()->assertJsonPath('data.target_location_id', $city->id);

        $this->bearer($token, $deviceId)->getJson('/api/v1/notifications?page[number]=1&page[size]=20')
            ->assertOk()->assertJsonPath('data.0.id', $notification->id);
        $this->bearer($token, $deviceId)
            ->withHeader('Idempotency-Key', 'm1-read-'.bin2hex(random_bytes(8)))
            ->postJson('/api/v1/notifications/'.$notification->id.'/read')
            ->assertOk()->assertJsonPath('data.read', true);

        $rotated = $this->bearer($token, $deviceId)
            ->postJson('/api/v1/auth/session/rotate')
            ->assertOk();
        $newToken = (string) $rotated->json('data.token');
        $this->assertNotSame($token, $newToken);

        $this->bearer($token, $deviceId)->getJson('/api/v1/me')->assertUnauthorized();
        $this->bearer($newToken, $deviceId)->getJson('/api/v1/me')
            ->assertOk()->assertJsonPath('data.id', $user->id);

        $this->bearer($newToken, $deviceId)->deleteJson('/api/v1/auth/session')->assertNoContent();
        $this->bearer($newToken, $deviceId)->getJson('/api/v1/me')->assertUnauthorized();
    }

    private function projectPayload(int $categoryId, int $targetLocationId): array
    {
        return [
            'title' => 'M1 Core Project',
            'project_type' => 'production',
            'project_visibility' => 'public',
            'project_stage' => 'documented',
            'investment_method' => 'auction_shares',
            'category_level1_id' => $categoryId,
            'problem_statement' => 'Core problem statement',
            'solution_description' => 'Core solution description',
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
            'summary' => 'M1 core project summary',
            'description' => 'M1 core project description',
            'target_location_id' => $targetLocationId,
        ];
    }

    private function bearer(string $token, string $deviceId): self
    {
        Auth::forgetGuards();

        return $this->withToken($token)->withHeader('X-Device-ID', $deviceId);
    }
}
