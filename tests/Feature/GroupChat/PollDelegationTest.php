<?php

namespace Tests\Feature\GroupChat;

use App\Models\Delegation;
use App\Models\ExperienceField;
use App\Models\Group;
use App\Models\GroupUser;
use App\Models\Poll;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use Tests\TestCase;

class PollDelegationTest extends TestCase
{
    use DatabaseTransactions;

    public function test_specialists_are_loaded_lazily_and_delegation_can_be_toggled(): void
    {
        $voter = $this->user('voter');
        $expert = $this->user('expert');
        $unqualified = $this->user('other');
        $group = Group::create(['group_type' => 'test', 'name' => 'Delegation ' . Str::uuid(), 'is_open' => true]);
        foreach ([$voter, $expert, $unqualified] as $member) {
            GroupUser::create(['group_id' => $group->id, 'user_id' => $member->id, 'role' => 1, 'status' => 1]);
        }
        $skill = ExperienceField::create(['name' => 'Skill ' . Str::uuid()]);
        $expert->experiences()->attach($skill->id);
        $poll = Poll::create([
            'group_id' => $group->id, 'created_by' => $voter->id, 'question' => 'Specialized?',
            'type' => 1, 'skill_id' => $skill->id, 'main_type' => 0, 'is_active' => true,
            'expires_at' => now()->addDay(),
        ]);

        $this->actingAs($voter)->getJson(route('groups.polls.experts', [$group, $poll]))
            ->assertOk()->assertJsonCount(1, 'data.experts')->assertJsonPath('data.experts.0.id', $expert->id);

        $url = route('groups.delegation', [$group, $poll, $expert]);
        $this->actingAs($voter)->postJson($url)->assertOk()->assertJsonPath('data.delegated', true);
        $this->assertDatabaseHas('delegations', ['poll_id' => $poll->id, 'user_id' => $voter->id, 'expert_id' => $expert->id]);
        $this->actingAs($voter)->postJson($url)->assertOk()->assertJsonPath('data.delegated', false);
        $this->assertSame(0, Delegation::where('poll_id', $poll->id)->where('user_id', $voter->id)->count());

        $this->actingAs($voter)->postJson(route('groups.delegation', [$group, $poll, $unqualified]))->assertStatus(422);
    }

    private function user(string $label): User
    {
        return User::create([
            'first_name' => ucfirst($label), 'last_name' => 'User',
            'email' => $label . '-' . Str::uuid() . '@example.test', 'password' => bcrypt('password123'),
        ]);
    }
}
