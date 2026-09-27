<?php

namespace Tests\Feature\Api\V1;

use App\Enums\Elections\ElectionLifecycleStatus;
use App\Models\Election;
use App\Models\ElectionEligibilitySnapshot;
use App\Models\GovernanceArea;
use App\Models\Group;
use App\Models\GroupSetting;
use App\Models\GroupUser;
use App\Models\NotificationSetting;
use App\Models\User;
use App\Notifications\GenericNotification;
use App\Modules\NajmBahar\Models\Project;
use App\Modules\NajmBahar\Models\ProjectCategory;
use App\Services\LocationGovernance\ResidenceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\Group as TestGroup;
use Tests\Support\LocationGovernance\LocationFixture;
use Tests\TestCase;

#[TestGroup('mysql-core-journeys')]
class ElectionProjectNotificationContractTest extends TestCase
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
            'queue.default' => 'sync',
            'broadcasting.default' => 'null',
        ]);
    }

    public function test_manager_keeps_personal_vote_rights_and_v1_ballot_reuses_canonical_election_service(): void
    {
        $manager = $this->member('manager');
        $candidate = $this->member('candidate');
        [$token, $deviceId] = $this->nativeSession($manager);

        $area = GovernanceArea::factory()->official()->create([
            'governance_type' => 'city',
            'status' => 'active',
            'canonical_name' => 'Election City',
        ]);
        $group = Group::create([
            'name' => 'Canonical Election Group',
            'group_type' => 0,
            'location_level' => 'city',
            'is_open' => 1,
            'governance_area_id' => $area->id,
            'dimension_key' => 'public',
            'dimension_value_key' => 'public',
        ]);
        GroupUser::create(['group_id' => $group->id, 'user_id' => $manager->id, 'role' => 3, 'status' => 1]);
        GroupUser::create(['group_id' => $group->id, 'user_id' => $candidate->id, 'role' => 1, 'status' => 1]);
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
        foreach ([$manager, $candidate] as $user) {
            ElectionEligibilitySnapshot::create([
                'election_id' => $election->id,
                'user_id' => $user->id,
                'voter_eligible' => true,
                'selectable_eligible' => true,
                'membership_role' => $user->is($manager) ? 3 : 1,
                'membership_status' => 1,
                'snapshot_version' => 1,
                'captured_at' => now(),
            ]);
        }

        $this->freshBearer($token, $deviceId)
            ->getJson('/api/v1/groups/'.$group->id.'/elections/current')
            ->assertOk()
            ->assertJsonPath('data.election.id', $election->id)
            ->assertJsonPath('data.membership.role', 3)
            ->assertJsonPath('data.permissions.can_vote', true);

        $key = 'ballot-'.bin2hex(random_bytes(8));
        $this->freshBearer($token, $deviceId)
            ->withHeader('Idempotency-Key', $key)
            ->putJson('/api/v1/elections/'.$election->id.'/ballot', [
                'manager_user_ids' => [$candidate->id],
                'inspector_user_ids' => [],
                'comment' => 'رأی آزمایشی قرارداد v1',
                'comment_visibility' => 'all_members',
            ])
            ->assertOk()
            ->assertJsonPath('data.election_id', $election->id)
            ->assertJsonPath('data.manager_user_ids.0', $candidate->id);

        $this->assertDatabaseHas('votes', [
            'election_id' => $election->id,
            'voter_id' => $manager->id,
            'candidate_user_id' => $candidate->id,
            'position' => '1',
        ]);
        $this->assertDatabaseHas('election_ballot_events', [
            'election_id' => $election->id,
            'voter_id' => $manager->id,
            'candidate_user_id' => $candidate->id,
            'comment_visibility' => 'all_members',
            'request_uuid' => $key,
        ]);
    }

    public function test_project_create_uses_explicit_canonical_target_independent_from_residence_and_allows_intermediate_stopping_point(): void
    {
        $user = $this->member('project');
        [$token, $deviceId] = $this->nativeSession($user);
        $schema = LocationFixture::iranSchema();

        $residencePath = LocationFixture::createPath($schema, ['country', 'province', 'county', 'section', 'city']);
        $residenceCity = $residencePath->last();
        app(ResidenceService::class)->setInitialPrimaryResidence($user, $residenceCity, ['source' => 'task8_contract']);

        $targetPath = LocationFixture::createPath($schema, ['country', 'province', 'county', 'section', 'city', 'urban_region', 'neighborhood']);
        $targetCity = $targetPath->firstWhere('level', 'city');
        $this->assertNotNull($targetCity);
        $this->assertNotSame($residenceCity->id, $targetCity->id);
        $this->assertTrue($targetPath->contains(fn ($location) => (int) $location->parent_id === (int) $targetCity->id));

        $targetArea = GovernanceArea::factory()->official()->create([
            'governance_type' => 'city',
            'status' => 'active',
            'canonical_name' => 'Project Target City',
        ]);
        $targetArea->locations()->attach($targetCity->id);

        $category = ProjectCategory::create([
            'name' => 'API v1 category',
            'level' => 1,
            'parent_id' => null,
            'status' => true,
        ]);

        $response = $this->freshBearer($token, $deviceId)
            ->withHeader('Idempotency-Key', 'project-'.bin2hex(random_bytes(8)))
            ->postJson('/api/v1/projects', $this->projectPayload($category->id, $targetCity->id))
            ->assertCreated()
            ->assertJsonPath('data.target_location_id', $targetCity->id)
            ->assertJsonPath('data.governance_area_id', $targetArea->id)
            ->assertJsonPath('data.status', 'draft');

        $projectId = (int) $response->json('data.id');
        $this->assertDatabaseHas('najm_bahar_projects', [
            'id' => $projectId,
            'owner_type' => User::class,
            'owner_id' => $user->id,
            'target_location_id' => $targetCity->id,
            'governance_area_id' => $targetArea->id,
        ]);

        $this->freshBearer($token, $deviceId)
            ->getJson('/api/v1/projects/'.$projectId)
            ->assertOk()
            ->assertJsonPath('data.id', $projectId)
            ->assertJsonPath('data.target_location_id', $targetCity->id);

        $this->assertSame($residenceCity->id, app(ResidenceService::class)->currentPrimaryResidence($user)->location_id);
    }

    public function test_notifications_are_owner_scoped_and_read_preferences_use_existing_settings(): void
    {
        $user = $this->member('notifications');
        $outsider = $this->member('notifications-outsider');
        [$token, $deviceId] = $this->nativeSession($user);

        $user->notify(new GenericNotification('برای من', 'پیام من', '/home', 'group.post'));
        $outsider->notify(new GenericNotification('برای دیگری', 'نباید دیده شود', '/home', 'group.post'));

        $mine = $user->notifications()->latest()->firstOrFail();
        $outsiderNotification = $outsider->notifications()->latest()->firstOrFail();

        $list = $this->freshBearer($token, $deviceId)
            ->getJson('/api/v1/notifications?page[number]=1&page[size]=20')
            ->assertOk()
            ->assertJsonPath('data.0.id', $mine->id);
        $this->assertFalse(collect($list->json('data'))->contains(fn ($row) => ($row['id'] ?? null) === $outsiderNotification->id));

        $this->freshBearer($token, $deviceId)
            ->withHeader('Idempotency-Key', 'notification-read-'.bin2hex(random_bytes(8)))
            ->postJson('/api/v1/notifications/'.$mine->id.'/read')
            ->assertOk()
            ->assertJsonPath('data.id', $mine->id)
            ->assertJsonPath('data.read', true);
        $this->assertNotNull($mine->fresh()->read_at);

        $defaults = NotificationSetting::forUser($user->id);
        $this->assertTrue($defaults->push_notifications);
        $this->freshBearer($token, $deviceId)
            ->withHeader('Idempotency-Key', 'notification-pref-'.bin2hex(random_bytes(8)))
            ->patchJson('/api/v1/notifications/preferences', [
                'push_notifications' => false,
            ])
            ->assertOk()
            ->assertJsonPath('data.push_notifications', false);
        $this->assertFalse(NotificationSetting::forUser($user->id)->fresh()->push_notifications);
    }

    private function projectPayload(int $categoryId, int $targetLocationId): array
    {
        return [
            'title' => 'API v1 scoped project',
            'project_type' => 'production',
            'project_visibility' => 'public',
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
            'summary' => 'Project summary',
            'description' => 'Project description',
            'target_location_id' => $targetLocationId,
        ];
    }

    private function member(string $suffix): User
    {
        return User::factory()->create([
            'email' => 'task8-'.$suffix.'-'.bin2hex(random_bytes(4)).'@example.test',
            'password' => Hash::make('secret-password'),
            'is_system' => false,
            'status' => 'active',
            'email_verified_at' => now(),
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

        return [$response->json('data.token'), $response->json('data.device.id')];
    }

    private function freshBearer(string $token, string $deviceId): self
    {
        Auth::forgetGuards();

        return $this->withToken($token)->withHeader('X-Device-ID', $deviceId);
    }
}
