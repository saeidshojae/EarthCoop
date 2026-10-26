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

class PollDelegatedVoteWeightTest extends TestCase
{
    use DatabaseTransactions;

    public function test_delegations_weight_the_experts_ballot_in_specialized_surveys_and_poll_style_elections(): void
    {
        config([
            'broadcasting.default' => 'null',
            'group-chat.features.transactional_outbox_v1' => false,
            'group-chat.features.realtime_polls' => false,
            'group-chat.transport' => 'polling',
        ]);

        [$group, $members, $skill] = $this->setupGroupWithMembers();
        [$delegatorOne, $delegatorTwo, $expert, $directVoter] = $members;

        foreach ([1 => 'specialized survey', 0 => 'specialized election'] as $mainType => $label) {
            $poll = Poll::create([
                'group_id' => $group->id,
                'created_by' => $directVoter->id,
                'question' => $label,
                'type' => 1,
                'skill_id' => $skill->id,
                'main_type' => $mainType,
                'is_active' => true,
                'expires_at' => now()->addDay(),
            ]);
            $yes = $poll->options()->create(['text' => 'Yes']);
            $no = $poll->options()->create(['text' => 'No']);

            Delegation::create(['poll_id' => $poll->id, 'user_id' => $delegatorOne->id, 'expert_id' => $expert->id]);
            Delegation::create(['poll_id' => $poll->id, 'user_id' => $delegatorTwo->id, 'expert_id' => $expert->id]);

            // Legacy ballots from before delegation must not be counted in addition
            // to the delegated expert's weighted ballot.
            PollVote::create(['poll_id' => $poll->id, 'user_id' => $delegatorOne->id, 'option_id' => $no->id]);
            PollVote::create(['poll_id' => $poll->id, 'user_id' => $delegatorTwo->id, 'option_id' => $no->id]);
            PollVote::create(['poll_id' => $poll->id, 'user_id' => $expert->id, 'option_id' => $yes->id]);

            $this->actingAs($directVoter)
                ->postJson(route('poll.vote', $poll), ['option_id' => $no->id])
                ->assertOk()
                ->assertJsonPath('poll.total_votes', 4)
                ->assertJsonPath('poll.options.0.count', 3)
                ->assertJsonPath('poll.options.1.count', 1);
        }
    }

    public function test_delegations_do_not_inflate_results_until_the_expert_casts_a_ballot(): void
    {
        config([
            'broadcasting.default' => 'null',
            'group-chat.features.transactional_outbox_v1' => false,
            'group-chat.features.realtime_polls' => false,
            'group-chat.transport' => 'polling',
        ]);

        [$group, $members, $skill] = $this->setupGroupWithMembers();
        [$delegatorOne, $delegatorTwo, $expert, $directVoter] = $members;

        $poll = Poll::create([
            'group_id' => $group->id,
            'created_by' => $directVoter->id,
            'question' => 'Delegations require an expert ballot',
            'type' => 1,
            'skill_id' => $skill->id,
            'main_type' => 1,
            'is_active' => true,
            'expires_at' => now()->addDay(),
        ]);
        $yes = $poll->options()->create(['text' => 'Yes']);
        Delegation::create(['poll_id' => $poll->id, 'user_id' => $delegatorOne->id, 'expert_id' => $expert->id]);
        Delegation::create(['poll_id' => $poll->id, 'user_id' => $delegatorTwo->id, 'expert_id' => $expert->id]);

        $this->actingAs($directVoter)
            ->postJson(route('poll.vote', $poll), ['option_id' => $yes->id])
            ->assertOk()
            ->assertJsonPath('poll.total_votes', 1)
            ->assertJsonPath('poll.options.0.count', 1);
    }

    public function test_delegated_member_cannot_submit_a_separate_direct_ballot(): void
    {
        config([
            'broadcasting.default' => 'null',
            'group-chat.features.transactional_outbox_v1' => false,
            'group-chat.features.realtime_polls' => false,
            'group-chat.transport' => 'polling',
        ]);

        [$group, $members, $skill] = $this->setupGroupWithMembers();
        [$delegator, , $expert] = $members;

        $poll = Poll::create([
            'group_id' => $group->id,
            'created_by' => $expert->id,
            'question' => 'Delegated ballot guard',
            'type' => 1,
            'skill_id' => $skill->id,
            'main_type' => 0,
            'is_active' => true,
            'expires_at' => now()->addDay(),
        ]);
        $option = $poll->options()->create(['text' => 'Yes']);
        Delegation::create(['poll_id' => $poll->id, 'user_id' => $delegator->id, 'expert_id' => $expert->id]);

        $this->actingAs($delegator)
            ->postJson(route('poll.vote', $poll), ['option_id' => $option->id])
            ->assertStatus(422);

        $this->assertDatabaseMissing('poll_votes', [
            'poll_id' => $poll->id,
            'user_id' => $delegator->id,
        ]);
    }

    private function setupGroupWithMembers(): array
    {
        $members = [
            $this->user('delegator-one'),
            $this->user('delegator-two'),
            $this->user('expert'),
            $this->user('direct-voter'),
        ];
        $group = Group::create([
            'group_type' => 'test',
            'name' => 'Weighted delegation ' . Str::uuid(),
            'is_open' => true,
        ]);

        foreach ($members as $member) {
            GroupUser::create([
                'group_id' => $group->id,
                'user_id' => $member->id,
                'role' => 1,
                'status' => 1,
            ]);
        }

        $skill = ExperienceField::create(['name' => 'Weighting skill ' . Str::uuid()]);
        $members[2]->experiences()->attach($skill->id);

        return [$group, $members, $skill];
    }

    private function user(string $label): User
    {
        return User::create([
            'first_name' => ucfirst($label),
            'last_name' => 'User',
            'email' => $label . '-' . Str::uuid() . '@example.test',
            'password' => bcrypt('password123'),
        ]);
    }
}
