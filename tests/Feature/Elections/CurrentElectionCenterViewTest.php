<?php

namespace Tests\Feature\Elections;

use App\Models\Election;
use App\Models\Group;
use App\Models\GroupUser;
use App\Models\Poll;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CurrentElectionCenterViewTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_current_center_renders_both_election_types_and_primary_actions(): void
    {
        $group = Group::create([
            'name' => 'گروه مرکز انتخابات',
            'group_type' => 'public',
            'location_level' => 'neighborhood',
            'is_open' => true,
        ]);
        $user = User::factory()->create(['is_system' => false]);
        GroupUser::create([
            'group_id' => $group->id,
            'user_id' => $user->id,
            'role' => 1,
            'status' => 1,
        ]);
        Election::create([
            'group_id' => $group->id,
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addDays(2),
            'is_closed' => false,
            'lifecycle_status' => 'open',
            'cycle_number' => 1,
        ]);
        Poll::create([
            'group_id' => $group->id,
            'created_by' => $user->id,
            'question' => 'انتخابات داخلی نمونه',
            'main_type' => 0,
            'is_active' => true,
            'expires_at' => now()->addDay(),
        ]);

        $response = $this->actingAs($user)->get(route('history.election'));

        $response->assertOk()
            ->assertSee('انتخابات جاری')
            ->assertSee('انتخابات سیستمی')
            ->assertSee('انتخابات درون‌گروهی')
            ->assertSee('تاریخچه انتخابات من')
            ->assertSee('نیازمند اقدام')
            ->assertSee('انتخابات داخلی نمونه')
            ->assertSee('ثبت رأی')
            ->assertSee('data-current-election-center', false)
            ->assertSee('data-election-type="systemic"', false)
            ->assertSee('data-election-type="internal"', false)
            ->assertSee('data-election-card', false)
            ->assertSee('data-primary-election-action', false);
    }
}
