<?php

namespace Tests\Feature\Api\V1;

use App\Enums\Membership\GroupCreationMode;
use App\Models\GovernanceArea;
use App\Models\Group;
use App\Models\GroupCreationPolicy;
use App\Models\GroupUser;
use App\Models\LocationScopedGroupRequest;
use App\Models\MembershipDimension;
use App\Models\User;
use App\Models\UserLocationRelationship;
use App\Services\GroupChat\GroupFeedService;
use App\Services\Membership\PublicDimensionResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\Group as TestGroup;
use Tests\Support\LocationGovernance\LocationFixture;
use Tests\TestCase;

#[TestGroup('mysql-group')]
class GroupContractTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'group-chat.features.feed_sequence_v1' => true,
            'group-chat.features.feed_unread_v1' => true,
            'group-chat.features.delta_sync_v1' => true,
            'group-chat.features.transactional_outbox_v1' => false,
        ]);
    }

    public function test_group_list_and_detail_expose_only_memberships_with_canonical_identity_and_authoritative_role(): void
    {
        $user = $this->member();
        [$token, $deviceId] = $this->nativeSession($user);
        $area = $this->area();
        $visible = $this->canonicalGroup($area, 'Visible Group', 'profession', 'engineer');
        $hidden = $this->canonicalGroup($area, 'Hidden Group', 'specialty', 'architect');
        GroupUser::create(['group_id' => $visible->id, 'user_id' => $user->id, 'role' => 2, 'status' => 1]);

        $list = $this->freshBearer($token, $deviceId)->getJson('/api/v1/groups')->assertOk();
        $this->assertSame([$visible->id], collect($list->json('data'))->pluck('id')->all());
        $list->assertJsonPath('data.0.identity.governance_area_id', $area->id)
            ->assertJsonPath('data.0.identity.dimension_key', 'profession')
            ->assertJsonPath('data.0.identity.dimension_value_key', 'engineer')
            ->assertJsonPath('data.0.membership.role', 2)
            ->assertJsonPath('data.0.membership.role_label', 'بازرس');

        $this->freshBearer($token, $deviceId)->getJson('/api/v1/groups/'.$visible->id)
            ->assertOk()
            ->assertJsonPath('data.membership.role', 2);

        $this->freshBearer($token, $deviceId)->getJson('/api/v1/groups/'.$hidden->id)
            ->assertNotFound();
    }

    public function test_group_list_includes_pending_location_shells_without_fake_group_identity(): void
    {
        config(['location-governance.groups_enabled' => true]);

        $user = $this->member();
        [$token, $deviceId] = $this->nativeSession($user);

        $dimension = MembershipDimension::query()->updateOrCreate(
            ['key' => 'public'],
            [
                'name' => 'Public',
                'resolver_class' => PublicDimensionResolver::class,
                'enabled' => true,
            ],
        );
        GroupCreationPolicy::query()->updateOrCreate(
            [
                'membership_dimension_id' => $dimension->id,
                'governance_area_id' => null,
            ],
            [
                'mode' => GroupCreationMode::Automatic,
                'enabled' => true,
            ],
        );

        $schema = LocationFixture::iranSchema();
        $residence = LocationFixture::createPath($schema, [
            'country',
            'province',
            'county',
            'section',
            'city',
            'urban_region',
            'neighborhood',
        ])->last();
        $area = $this->area();
        $area->locations()->attach($residence->id);
        UserLocationRelationship::create([
            'user_id' => $user->id,
            'location_id' => $residence->id,
            'relationship_type' => 'primary_residence',
            'started_at' => now(),
            'metadata' => [],
        ]);

        $active = $this->canonicalGroup($area, 'Active Group', 'public', 'public');
        GroupUser::create(['group_id' => $active->id, 'user_id' => $user->id, 'role' => 1, 'status' => 1]);

        $pending = LocationScopedGroupRequest::create([
            'requester_user_id' => $user->id,
            'scope_kind' => 'official_system',
            'dimension_key' => 'public',
            'dimension_value_key' => 'public',
            'status' => 'pending_location',
            'metadata' => [
                'type_key' => 'neighborhood',
                'canonical_name' => 'محله در انتظار',
                'is_pending_base' => true,
            ],
        ]);

        $response = $this->freshBearer($token, $deviceId)
            ->getJson('/api/v1/groups')
            ->assertOk();

        $items = collect($response->json('data'));
        $this->assertCount(2, $items);
        $this->assertSame($active->id, $items->firstWhere('pending', false)['id']);

        $pendingItem = $items->firstWhere('pending', true);
        $this->assertNotNull($pendingItem);
        $this->assertNull($pendingItem['id']);
        $this->assertNull($pendingItem['identity']['governance_area_id']);
        $this->assertSame($pending->id, $pendingItem['pending_request_id']);
        $this->assertSame('مجمع عمومی محله در انتظار', $pendingItem['name']);
        $this->assertSame('فعال', $pendingItem['membership']['role_label']);
        $this->assertFalse($pendingItem['can_open']);
    }

    public function test_feed_delta_preserves_sequence_event_identity_and_duplicate_recording_semantics(): void
    {
        $viewer = $this->member();
        $actor = $this->member();
        [$token, $deviceId] = $this->nativeSession($viewer);
        $group = $this->canonicalGroup($this->area(), 'Feed Group', 'public', 'public');
        GroupUser::create(['group_id' => $group->id, 'user_id' => $viewer->id, 'role' => 1, 'status' => 1]);
        GroupUser::create(['group_id' => $group->id, 'user_id' => $actor->id, 'role' => 1, 'status' => 1]);

        $feed = app(GroupFeedService::class);
        $first = $feed->record($group->id, 'message', 91001, $actor->id);
        $duplicate = $feed->record($group->id, 'message', 91001, $actor->id);
        $second = $feed->record($group->id, 'post', 91002, $actor->id);
        $this->assertSame($first->id, $duplicate->id);
        $this->assertSame(2, (int) $second->sequence);

        $page1 = $this->freshBearer($token, $deviceId)
            ->getJson('/api/v1/groups/'.$group->id.'/feed/delta?after_sequence=0&limit=1')
            ->assertOk();
        $page1->assertJsonPath('data.events.0.sequence', 1)
            ->assertJsonPath('data.events.0.event_id', 'feed:'.$first->id.':v1')
            ->assertJsonPath('data.latest_sequence', 1)
            ->assertJsonPath('data.has_more', true);

        $page2 = $this->freshBearer($token, $deviceId)
            ->getJson('/api/v1/groups/'.$group->id.'/feed/delta?after_sequence=1&limit=100')
            ->assertOk();
        $page2->assertJsonPath('data.events.0.sequence', 2)
            ->assertJsonPath('data.has_more', false);
    }

    public function test_unread_cursor_and_idempotent_mark_read_reuse_mature_feed_service(): void
    {
        $viewer = $this->member();
        $actor = $this->member();
        [$token, $deviceId] = $this->nativeSession($viewer);
        $group = $this->canonicalGroup($this->area(), 'Unread Group', 'public', 'public');
        GroupUser::create(['group_id' => $group->id, 'user_id' => $viewer->id, 'role' => 1, 'status' => 1]);
        GroupUser::create(['group_id' => $group->id, 'user_id' => $actor->id, 'role' => 1, 'status' => 1]);
        $feed = app(GroupFeedService::class);
        $feed->record($group->id, 'post', 92001, $actor->id);
        $feed->record($group->id, 'poll', 92002, $actor->id);

        $this->freshBearer($token, $deviceId)->getJson('/api/v1/groups/'.$group->id.'/unread')
            ->assertOk()
            ->assertJsonPath('data.total', 2)
            ->assertJsonPath('data.cursor', 0)
            ->assertJsonPath('data.first_unread_sequence', 1);

        $key = 'group-read-'.bin2hex(random_bytes(8));
        $first = $this->freshBearer($token, $deviceId)
            ->withHeader('Idempotency-Key', $key)
            ->postJson('/api/v1/groups/'.$group->id.'/read', ['through_sequence' => 1])
            ->assertOk()
            ->assertJsonPath('data.cursor', 1)
            ->assertJsonPath('data.unread.total', 1);

        $replayed = $this->freshBearer($token, $deviceId)
            ->withHeader('Idempotency-Key', $key)
            ->postJson('/api/v1/groups/'.$group->id.'/read', ['through_sequence' => 1])
            ->assertOk();

        $this->assertSame($first->json('data'), $replayed->json('data'));
        $this->assertSame($first->json('status'), $replayed->json('status'));
        $this->assertSame($first->json('meta'), $replayed->json('meta'));
        $this->assertNotSame($first->json('request_id'), $replayed->json('request_id'));
        $this->assertSame('true', strtolower((string) $replayed->headers->get('Idempotency-Replayed')));
    }

    private function canonicalGroup(GovernanceArea $area, string $name, string $dimension, string $value): Group
    {
        return Group::create([
            'group_type' => 'test',
            'name' => $name,
            'is_open' => 1,
            'governance_area_id' => $area->id,
            'dimension_key' => $dimension,
            'dimension_value_key' => $value,
        ]);
    }

    private function area(): GovernanceArea
    {
        return GovernanceArea::create([
            'key' => 'api-v1-'.bin2hex(random_bytes(5)),
            'country_code' => 'IR',
            'governance_type' => 'city',
            'area_kind' => 'official',
            'canonical_name' => 'API v1 Test Area',
            'rank' => 7,
            'status' => 'active',
        ]);
    }

    private function member(): User
    {
        return User::factory()->create([
            'email' => 'group-mobile-'.bin2hex(random_bytes(4)).'@example.test',
            'password' => Hash::make('secret-password'),
            'is_system' => false,
            'status' => 'active',
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
