<?php

namespace Tests\Feature\GroupChat;

use App\Models\Group;
use App\Models\GroupUser;
use App\Models\Poll;
use App\Models\PollVote;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use Tests\TestCase;

class PollVoteToggleTest extends TestCase
{
    use DatabaseTransactions;

    public function test_clicking_the_selected_poll_option_removes_the_vote(): void
    {
        config([
            'broadcasting.default' => 'null',
            'group-chat.features.transactional_outbox_v1' => false,
            'group-chat.transport' => 'polling',
        ]);

        $user = User::create([
            'first_name' => 'Poll',
            'last_name' => 'Voter',
            'email' => 'poll-voter-' . Str::uuid() . '@example.test',
            'password' => bcrypt('password123'),
        ]);
        $group = Group::create([
            'group_type' => 'test',
            'name' => 'Poll toggle ' . Str::uuid(),
            'is_open' => true,
        ]);
        GroupUser::create([
            'group_id' => $group->id,
            'user_id' => $user->id,
            'role' => 1,
            'status' => 1,
        ]);
        $poll = Poll::create([
            'group_id' => $group->id,
            'created_by' => $user->id,
            'question' => 'Toggle this vote?',
            'is_active' => true,
            'expires_at' => now()->addDay(),
        ]);
        $option = $poll->options()->create(['text' => 'Yes']);

        $this->actingAs($user)
            ->postJson(route('poll.vote', $poll), ['option_id' => $option->id])
            ->assertOk()
            ->assertJsonPath('vote_removed', false)
            ->assertJsonPath('poll.user_option_id', $option->id)
            ->assertJsonPath('poll.total_votes', 1);

        $this->assertDatabaseHas('poll_votes', [
            'poll_id' => $poll->id,
            'user_id' => $user->id,
            'option_id' => $option->id,
        ]);
        $this->assertDatabaseHas('group_sync_events', [
            'group_id' => $group->id,
            'action' => 'poll_voted',
            'content_type' => 'poll',
            'content_id' => $poll->id,
        ]);

        $this->actingAs($user)
            ->postJson(route('poll.vote', $poll), ['option_id' => $option->id])
            ->assertOk()
            ->assertJsonPath('vote_removed', true)
            ->assertJsonPath('poll.user_option_id', null)
            ->assertJsonPath('poll.total_votes', 0);

        $this->assertSame(0, PollVote::where('poll_id', $poll->id)->where('user_id', $user->id)->count());
    }
}
