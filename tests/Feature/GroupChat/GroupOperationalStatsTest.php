<?php

namespace Tests\Feature\GroupChat;

use App\Models\Group;
use App\Models\GroupUser;
use App\Models\Poll;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GroupOperationalStatsTest extends TestCase
{
    use RefreshDatabase;

    public function test_manager_can_load_operational_stats_using_canonical_poll_expiry_column(): void
    {
        $manager = User::factory()->create();
        $group = Group::create([
            'name' => 'گروه آزمون آمار عملیاتی',
            'group_type' => 'public',
            'location_level' => 'neighborhood',
        ]);

        GroupUser::create([
            'group_id' => $group->id,
            'user_id' => $manager->id,
            'role' => 3,
            'status' => 1,
        ]);

        $this->poll($group, $manager, 1, now()->addDay());
        $this->poll($group, $manager, 1, now()->subDay());
        $this->poll($group, $manager, 1, null);
        $this->poll($group, $manager, 0, now()->addDay());
        $this->poll($group, $manager, 0, now()->subDay());
        $this->poll($group, $manager, 0, null);

        $response = $this->actingAs($manager)
            ->getJson(route('groups.stats', $group));

        $response->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('stats.polls.total', 3)
            ->assertJsonPath('stats.polls.active', 2)
            ->assertJsonPath('stats.polls.expired', 1)
            ->assertJsonPath('stats.elections.total', 3)
            ->assertJsonPath('stats.elections.active', 2)
            ->assertJsonPath('stats.elections.closed', 1);
    }

    private function poll(Group $group, User $creator, int $mainType, $expiresAt): Poll
    {
        return Poll::create([
            'group_id' => $group->id,
            'created_by' => $creator->id,
            'question' => 'نمونه آمار عملیاتی',
            'main_type' => $mainType,
            'is_active' => true,
            'expires_at' => $expiresAt,
        ]);
    }
}
