<?php

namespace Tests\Feature\Api\V1;

use App\Models\Group;
use App\Models\GroupUser;
use App\Models\Message;
use App\Models\User;
use App\Services\MembershipParticipationEligibilityService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\Group as TestGroup;
use Tests\TestCase;

#[TestGroup('mysql-group')]
class GroupMessageContractTest extends TestCase
{
    use RefreshDatabase;

    private User $member;
    private Group $group;
    private string $token;
    private string $device;

    protected function setUp(): void
    {
        parent::setUp();
        config(['broadcasting.default' => 'null', 'group-chat.features.feed_sequence_v1' => true, 'group-chat.features.delta_sync_v1' => true,
            'group-chat.features.transactional_outbox_v1' => false]);
        Event::fake([\App\Events\MessageCreated::class]);
        $this->member = User::factory()->create(['status' => 'active', 'is_system' => false, 'password' => Hash::make('message-password')]);
        $area = \App\Models\GovernanceArea::create(['key' => 'native-message-test', 'country_code' => 'IR', 'governance_type' => 'city', 'area_kind' => 'official', 'canonical_name' => 'Test', 'rank' => 7, 'status' => 'active']);
        $this->group = Group::create(['name' => 'Native messages', 'group_type' => 'test', 'is_open' => true, 'governance_area_id' => $area->id, 'dimension_key' => 'public', 'dimension_value_key' => 'all']);
        GroupUser::create(['group_id' => $this->group->id, 'user_id' => $this->member->id, 'role' => 1, 'status' => 1]);
        $this->mock(MembershipParticipationEligibilityService::class)->shouldReceive('status')->andReturn(MembershipParticipationEligibilityService::ELIGIBLE);
        $login = $this->postJson('/api/v1/auth/session', ['email' => $this->member->email, 'password' => 'message-password',
            'platform' => 'android', 'app_version' => '1.0.0', 'locale' => 'fa', 'push_capable' => false])->assertCreated();
        $this->token = $login->json('data.token');
        $this->device = $login->json('data.device.id');
    }

    private function send(array $data, string $key = 'native-message-key-0001', ?Group $group = null)
    {
        Auth::forgetGuards();
        return $this->withToken($this->token)->withHeaders(['X-Device-ID' => $this->device, 'Idempotency-Key' => $key])
            ->postJson('/api/v1/groups/'.($group ?? $this->group)->id.'/messages', $data);
    }

    public function test_member_sends_once_and_receives_a_native_projection(): void
    {
        $first = $this->send(['message' => 'سلام <script>bad</script>'])->assertCreated()
            ->assertJsonPath('meta.api_version', 'v1')->assertJsonPath('data.group_id', $this->group->id);
        $again = $this->send(['message' => 'سلام <script>bad</script>'])->assertCreated();
        $this->assertSame($first->json('data.id'), $again->json('data.id'));
        $this->assertSame(1, Message::where('group_id', $this->group->id)->count());
        $this->assertStringNotContainsString('<script>', Message::first()->message);
        $this->assertDatabaseHas('group_feed_items', ['group_id' => $this->group->id, 'content_id' => $first->json('data.id')]);
    }

    public function test_multiline_text_keeps_exact_line_breaks_in_acknowledgement_and_feed(): void
    {
        $text = "first\n\nsecond & <literal>";
        $this->send(['message' => $text])->assertCreated()->assertJsonPath('data.message', $text);
        Auth::forgetGuards();
        $this->withToken($this->token)->withHeader('X-Device-ID', $this->device)
            ->getJson('/api/v1/groups/'.$this->group->id.'/feed/delta?window=latest')
            ->assertOk()->assertJsonPath('data.events.0.payload.message', $text);
    }

    public function test_legacy_break_only_html_keeps_one_line_break_in_native_feed(): void
    {
        $message = Message::create(['group_id' => $this->group->id, 'user_id' => $this->member->id, 'message' => 'first<br />second']);
        app(\App\Services\GroupChat\GroupFeedService::class)->record($this->group->id, 'message', $message->id, $this->member->id, now());
        Auth::forgetGuards();
        $this->withToken($this->token)->withHeader('X-Device-ID', $this->device)
            ->getJson('/api/v1/groups/'.$this->group->id.'/feed/delta?window=latest')
            ->assertOk()->assertJsonPath('data.events.0.payload.message', "first\nsecond");
    }

    public function test_latest_feed_window_includes_new_messages_after_a_long_history(): void
    {
        $feed = app(\App\Services\GroupChat\GroupFeedService::class);
        for ($i = 1; $i <= 25; $i++) {
            $message = Message::create(['group_id' => $this->group->id, 'user_id' => $this->member->id, 'message' => 'message-'.$i]);
            $feed->record($this->group->id, 'message', $message->id, $this->member->id, now());
        }
        Auth::forgetGuards();
        $this->withToken($this->token)->withHeaders(['X-Device-ID' => $this->device])
            ->getJson('/api/v1/groups/'.$this->group->id.'/feed/delta?window=latest&limit=20')
            ->assertOk()->assertJsonCount(20, 'data.events')->assertJsonPath('data.latest_sequence', 25)
            ->assertJsonPath('data.events.0.sequence', 6)->assertJsonPath('data.has_more', false);
    }

    public function test_zero_is_a_valid_non_empty_text_message(): void
    {
        $this->send(['message' => '0'])->assertCreated()->assertJsonPath('data.message', '0');
        $this->assertSame(1, Message::count());
    }

    public function test_observer_cannot_send_even_in_open_group(): void
    {
        GroupUser::where('group_id', $this->group->id)->where('user_id', $this->member->id)->update(['role' => 0]);
        $this->send(['message' => 'blocked'])->assertForbidden()->assertJsonPath('error.code', 'observer_read_only');
        $this->assertSame(0, Message::count());
    }

    public function test_closed_session_blocks_an_ordinary_member(): void
    {
        $this->group->update(['is_open' => false]);
        $this->send(['message' => 'blocked'])->assertForbidden();
        $this->assertSame(0, Message::count());
    }

    public function test_membership_payment_gate_is_not_bypassed(): void
    {
        $this->mock(MembershipParticipationEligibilityService::class)->shouldReceive('status')->andReturn(MembershipParticipationEligibilityService::MEMBERSHIP_FEE_DUE);
        $this->send(['message' => 'blocked'])->assertForbidden()->assertJsonPath('error.code', 'membership_fee_due');
        $this->assertSame(0, Message::count());
    }

    public function test_same_key_cannot_replay_a_message_into_a_different_group(): void
    {
        $other = Group::create(['name' => 'Other', 'group_type' => 'test', 'is_open' => true]);
        GroupUser::create(['group_id' => $other->id, 'user_id' => $this->member->id, 'role' => 1, 'status' => 1]);
        $this->send(['message' => 'hello'])->assertCreated();
        $this->send(['message' => 'hello'], group: $other)->assertConflict();
        $this->assertSame(0, Message::where('group_id', $other->id)->count());
    }

    public function test_revoked_membership_cannot_replay_a_previous_success(): void
    {
        $this->send(['message' => 'hello'])->assertCreated();
        GroupUser::where('group_id', $this->group->id)->where('user_id', $this->member->id)->delete();
        $this->send(['message' => 'hello'])->assertForbidden();
        $this->assertSame(1, Message::count());
    }

    public function test_a_throttled_intent_can_retry_after_the_limit_clears(): void
    {
        $key = 'send-message:'.$this->member->id.':'.$this->group->id;
        for ($i = 0; $i < 10; $i++) {
            \Illuminate\Support\Facades\RateLimiter::hit($key, 60);
        }
        $this->send(['message' => 'later'])->assertStatus(429)->assertJsonPath('error.retryable', true);
        \Illuminate\Support\Facades\RateLimiter::clear($key);
        $this->send(['message' => 'later'])->assertCreated();
        $this->assertSame(1, Message::count());
    }

    public function test_body_group_cannot_override_route_and_unsupported_uploads_are_rejected(): void
    {
        $this->send(['message' => 'hello', 'group_id' => 99999])->assertCreated()->assertJsonPath('data.group_id', $this->group->id);
        $this->send(['message' => 'hello', 'parent_id' => 1], 'native-message-key-0002')->assertUnprocessable();
        $this->send(['message' => '   '], 'native-message-key-0003')->assertUnprocessable();
    }
}
