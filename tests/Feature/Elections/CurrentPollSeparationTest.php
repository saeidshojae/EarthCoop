<?php

namespace Tests\Feature\Elections;

use App\Models\Group;
use App\Models\GroupUser;
use App\Models\Poll;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CurrentPollSeparationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_internal_elections_live_in_election_center_not_current_polls(): void
    {
        $group = Group::create([
            'name' => 'تفکیک نظرسنجی و انتخابات',
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

        $internalQuestion = 'انتخابات داخلی مخصوص تست جداسازی';
        $pollQuestion = 'نظرسنجی عادی مخصوص تست جداسازی';

        Poll::create([
            'group_id' => $group->id,
            'created_by' => $user->id,
            'question' => $internalQuestion,
            'main_type' => 0,
            'is_active' => true,
            'expires_at' => now()->addDay(),
        ]);
        Poll::create([
            'group_id' => $group->id,
            'created_by' => $user->id,
            'question' => $pollQuestion,
            'main_type' => 1,
            'is_active' => true,
            'expires_at' => now()->addDay(),
        ]);

        $this->actingAs($user)
            ->get(route('history.poll'))
            ->assertOk()
            ->assertSee($pollQuestion)
            ->assertDontSee($internalQuestion);

        $this->actingAs($user)
            ->get(route('history.election'))
            ->assertOk()
            ->assertSee($internalQuestion)
            ->assertDontSee($pollQuestion);
    }
}
