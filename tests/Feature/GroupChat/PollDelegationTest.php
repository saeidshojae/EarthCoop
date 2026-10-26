<?php

namespace Tests\Feature\GroupChat;

use App\Models\Delegation;
use App\Models\ExperienceField;
use App\Models\Group;
use App\Models\GroupUser;
use App\Models\Poll;
use App\Models\PollVote;
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
        $voter->experiences()->attach($skill->id);
        $poll = Poll::create([
            'group_id' => $group->id, 'created_by' => $voter->id, 'question' => 'Specialized?',
            'type' => 1, 'skill_id' => $skill->id, 'main_type' => 0, 'is_active' => true,
            'expires_at' => now()->addDay(),
        ]);
        $yes = $poll->options()->create(['text' => 'Yes']);
        $no = $poll->options()->create(['text' => 'No']);
        PollVote::create(['poll_id' => $poll->id, 'user_id' => $voter->id, 'option_id' => $yes->id]);
        PollVote::create(['poll_id' => $poll->id, 'user_id' => $expert->id, 'option_id' => $no->id]);

        $this->actingAs($voter)->getJson(route('groups.polls.experts', [$group, $poll]))
            ->assertOk()->assertJsonCount(1, 'data.experts')->assertJsonPath('data.experts.0.id', $expert->id);

        $url = route('groups.delegation', [$group, $poll, $expert]);
        $this->actingAs($voter)->postJson($url)
            ->assertOk()
            ->assertJsonPath('data.delegated', true)
            ->assertJsonPath('data.poll.total_votes', 2)
            ->assertJsonPath('data.poll.options.0.count', 0)
            ->assertJsonPath('data.poll.options.1.count', 2);
        $this->assertDatabaseHas('delegations', ['poll_id' => $poll->id, 'user_id' => $voter->id, 'expert_id' => $expert->id]);
        $this->assertDatabaseMissing('poll_votes', ['poll_id' => $poll->id, 'user_id' => $voter->id]);

        // Keep delegation one-level: a recipient who is delegating out cannot
        // receive another delegation, and an expert with incoming delegations
        // cannot pass their own vote onward.
        $this->actingAs($unqualified)
            ->postJson(route('groups.delegation', [$group, $poll, $voter]))
            ->assertStatus(422);
        $this->actingAs($expert)
            ->postJson(route('groups.delegation', [$group, $poll, $voter]))
            ->assertStatus(422);

        $this->actingAs($voter)->postJson($url)
            ->assertOk()
            ->assertJsonPath('data.delegated', false)
            ->assertJsonPath('data.poll.total_votes', 1)
            ->assertJsonPath('data.poll.options.1.count', 1);
        $this->assertSame(0, Delegation::where('poll_id', $poll->id)->where('user_id', $voter->id)->count());

        $this->actingAs($voter)->postJson(route('groups.delegation', [$group, $poll, $unqualified]))->assertStatus(422);
    }

    public function test_expert_picker_ignores_stale_outgoing_delegations_when_the_target_is_no_longer_qualified(): void
    {
        $voter = $this->user('picker-voter');
        $candidate = $this->user('picker-candidate');
        $formerExpert = $this->user('former-expert');
        $group = Group::create(['group_type' => 'test', 'name' => 'Delegation picker ' . Str::uuid(), 'is_open' => true]);

        foreach ([$voter, $candidate, $formerExpert] as $member) {
            GroupUser::create(['group_id' => $group->id, 'user_id' => $member->id, 'role' => 1, 'status' => 1]);
        }

        $skill = ExperienceField::create(['name' => 'Picker skill ' . Str::uuid()]);
        $candidate->experiences()->attach($skill->id);
        $poll = Poll::create([
            'group_id' => $group->id, 'created_by' => $voter->id, 'question' => 'Stale delegation?',
            'type' => 1, 'skill_id' => $skill->id, 'main_type' => 1, 'is_active' => true,
            'expires_at' => now()->addDay(),
        ]);

        // This row is stale because the former target no longer qualifies in the poll's skill.
        Delegation::create(['poll_id' => $poll->id, 'user_id' => $candidate->id, 'expert_id' => $formerExpert->id]);

        $this->actingAs($voter)->getJson(route('groups.polls.experts', [$group, $poll]))
            ->assertOk()
            ->assertJsonFragment(['id' => $candidate->id, 'selected' => false]);
    }

    private function user(string $label): User
    {
        return User::create([
            'first_name' => ucfirst($label), 'last_name' => 'User',
            'email' => $label . '-' . Str::uuid() . '@example.test', 'password' => bcrypt('password123'),
        ]);
    }
}
