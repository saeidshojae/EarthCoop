<?php

namespace Tests\Feature\GroupChat;

use App\Models\Group;
use App\Models\GroupUser;
use App\Models\Blog;
use App\Models\Election;
use App\Models\Message;
use App\Models\Poll;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class MessageAuthorizationTest extends TestCase
{
    use DatabaseTransactions;

    private static int $userSequence = 0;

    protected function setUp(): void
    {
        parent::setUp();

        $this->bootstrapGroupChatSchema();

        config(['broadcasting.default' => 'null']);
    }

    private function bootstrapGroupChatSchema(): void
    {
        if (! Schema::hasTable('users')) {
            Schema::create('users', function (Blueprint $table) {
                $table->id();
                $table->string('first_name')->nullable();
                $table->string('last_name')->nullable();
                $table->string('email')->unique();
                $table->string('password');
                $table->boolean('is_admin')->default(false);
                $table->timestamp('last_seen')->nullable();
                $table->timestamp('email_verified_at')->nullable();
                $table->rememberToken();
                $table->timestamps();
            });
        } elseif (! Schema::hasColumn('users', 'last_seen')) {
            Schema::table('users', function (Blueprint $table) {
                $table->timestamp('last_seen')->nullable();
            });
        }

        if (! Schema::hasTable('groups')) {
            Schema::create('groups', function (Blueprint $table) {
                $table->id();
                $table->string('group_type')->nullable();
                $table->string('name');
                $table->boolean('is_open')->default(true);
                $table->timestamp('last_activity_at')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('group_user')) {
            Schema::create('group_user', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('group_id');
                $table->unsignedBigInteger('user_id');
                $table->integer('role')->default(1);
                $table->integer('status')->default(1);
                $table->boolean('expired')->default(false);
                $table->unsignedBigInteger('last_read_message_id')->nullable();
                $table->timestamps();
                $table->softDeletes();
            });
        }
        if (! Schema::hasColumn('group_user', 'session_write_allowed')) {
            Schema::table('group_user', function (Blueprint $table) {
                $table->boolean('session_write_allowed')->default(false);
            });
        }

        if (! Schema::hasTable('roles')) {
            Schema::create('roles', function (Blueprint $table) {
                $table->id();
                $table->string('slug')->unique();
                $table->string('name')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('user_role')) {
            Schema::create('user_role', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('user_id');
                $table->unsignedBigInteger('role_id');
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('messages')) {
            Schema::create('messages', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('group_id');
                $table->unsignedBigInteger('user_id');
                $table->text('message')->nullable();
                $table->string('parent_id')->nullable();
                $table->unsignedBigInteger('thread_id')->nullable();
                $table->unsignedInteger('reply_count')->default(0);
                $table->string('file_path')->nullable();
                $table->string('file_type')->nullable();
                $table->string('file_name')->nullable();
                $table->string('voice_message')->nullable();
                $table->string('client_message_id', 100)->nullable();
                $table->boolean('edited')->default(false);
                $table->unsignedBigInteger('edited_by')->nullable();
                $table->unsignedBigInteger('removed_by')->nullable();
                $table->json('read_by')->nullable();
                $table->timestamps();
                $table->unique(['group_id', 'user_id', 'client_message_id'], 'messages_group_user_client_message_id_unique');
            });
        } elseif (! Schema::hasColumn('messages', 'client_message_id')) {
            Schema::table('messages', function (Blueprint $table) {
                $table->string('client_message_id', 100)->nullable();
            });
        }

        if (! Schema::hasTable('blogs')) {
            Schema::create('blogs', function (Blueprint $table) {
                $table->id();
                $table->string('title');
                $table->text('content');
                $table->string('img')->nullable();
                $table->unsignedBigInteger('user_id');
                $table->unsignedBigInteger('group_id');
                $table->unsignedBigInteger('category_id');
                $table->string('file_type')->nullable();
                $table->json('read_by')->nullable();
                $table->timestamps();
                $table->softDeletes();
            });
        }

        if (! Schema::hasTable('polls')) {
            Schema::create('polls', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('group_id');
                $table->unsignedBigInteger('created_by');
                $table->unsignedBigInteger('skill_id')->nullable();
                $table->string('question');
                $table->boolean('is_multiple')->default(false);
                $table->boolean('is_anonymous')->default(false);
                $table->boolean('is_active')->default(true);
                $table->boolean('show_results')->default(true);
                $table->integer('type')->default(0);
                $table->integer('main_type')->default(0);
                $table->json('read_by')->nullable();
                $table->timestamp('expires_at')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('group_session_participation_requests')) {
            Schema::create('group_session_participation_requests', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('group_id');
                $table->unsignedBigInteger('user_id');
                $table->string('status')->default('pending');
                $table->string('message', 300)->nullable();
                $table->unsignedBigInteger('resolved_by')->nullable();
                $table->timestamp('resolved_at')->nullable();
                $table->timestamps();
                $table->unique(['group_id', 'user_id']);
            });
        }

        if (! Schema::hasTable('group_sessions')) {
            Schema::create('group_sessions', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('group_id');
                $table->unsignedBigInteger('created_by');
                $table->unsignedBigInteger('ended_by')->nullable();
                $table->string('title', 160);
                $table->text('subject')->nullable();
                $table->text('agenda')->nullable();
                $table->string('status')->default('scheduled');
                $table->timestamp('starts_at');
                $table->timestamp('started_at')->nullable();
                $table->timestamp('ended_at')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('notifications')) {
            Schema::create('notifications', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->string('type');
                $table->string('notifiable_type');
                $table->unsignedBigInteger('notifiable_id');
                $table->text('data');
                $table->timestamp('read_at')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('pinned_messages')) {
            Schema::create('pinned_messages', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('message_id')->nullable();
                $table->unsignedBigInteger('group_id');
                $table->string('content_type', 40)->nullable();
                $table->unsignedBigInteger('content_id')->nullable();
                $table->unsignedBigInteger('pinned_by');
                $table->unsignedBigInteger('announcement_id')->nullable();
                $table->timestamps();
                $table->unique(['group_id', 'content_type', 'content_id']);
            });
        } elseif (! Schema::hasColumn('pinned_messages', 'content_type')) {
            Schema::table('pinned_messages', function (Blueprint $table) {
                $table->string('content_type', 40)->nullable();
                $table->unsignedBigInteger('content_id')->nullable();
            });
        }
        if (Schema::hasTable('pinned_messages') && Schema::hasColumn('pinned_messages', 'message_id')) {
            Schema::table('pinned_messages', function (Blueprint $table) {
                $table->unsignedBigInteger('message_id')->nullable()->change();
            });
        }
    }

    public function test_message_owner_can_edit_message(): void
    {
        [$group, $owner] = $this->makeGroupWithMember(1);
        $message = $this->makeMessage($group, $owner);

        $response = $this->actingAs($owner)->postJson(route('groups.messages.edit', $message), [
            'content' => 'edited content',
        ]);

        $response->assertOk()->assertJsonFragment([
            'status' => 'success',
            'edited' => true,
        ]);

        $this->assertDatabaseHas('messages', [
            'id' => $message->id,
            'message' => 'edited content',
            'edited' => 1,
            'edited_by' => $owner->id,
        ]);
    }

    public function test_non_member_cannot_edit_message(): void
    {
        [$group, $owner] = $this->makeGroupWithMember(1);
        $message = $this->makeMessage($group, $owner);
        $other = $this->makeUser();

        $response = $this->actingAs($other)->postJson(route('groups.messages.edit', $message), [
            'content' => 'unauthorized edit',
        ]);

        $response->assertStatus(403);

        $this->assertDatabaseHas('messages', [
            'id' => $message->id,
            'message' => 'original message',
        ]);
    }

    public function test_non_member_cannot_read_group_feed_unread_or_search(): void
    {
        [$group] = $this->makeGroupWithMember();
        $outsider = $this->makeUser();

        $this->actingAs($outsider)
            ->getJson('/api/groups/' . $group->id . '/messages')
            ->assertForbidden();

        $this->actingAs($outsider)
            ->getJson(route('groups.unread-count', $group))
            ->assertForbidden();

        $this->actingAs($outsider)
            ->getJson(route('groups.search', ['group' => $group, 'query' => 'secret']))
            ->assertForbidden();
    }

    public function test_non_member_cannot_download_private_group_message_media(): void
    {
        [$group, $owner] = $this->makeGroupWithMember();
        $outsider = $this->makeUser();
        $message = $this->makeMessage($group, $owner, [
            'file_path' => 'group-chat/messages/' . $group->id . '/secret.pdf',
            'file_name' => 'secret.pdf',
            'file_type' => 'application/pdf',
        ]);

        $this->actingAs($outsider)
            ->get(route('groups.messages.file', $message))
            ->assertForbidden();
    }

    public function test_delete_decrements_thread_reply_count(): void
    {
        [$group, $owner] = $this->makeGroupWithMember(1);
        $root = $this->makeMessage($group, $owner, [
            'reply_count' => 1,
        ]);
        $reply = $this->makeMessage($group, $owner, [
            'parent_id' => $root->id,
            'thread_id' => $root->id,
        ]);

        $response = $this->actingAs($owner)->postJson(route('groups.messages.delete', $reply));

        $response->assertOk()->assertJsonFragment([
            'status' => 'success',
            'deleted' => true,
        ]);

        $this->assertDatabaseHas('messages', [
            'id' => $reply->id,
            'lifecycle_state' => 'deleted',
            'message' => null,
        ]);
        $this->assertFalse(Message::query()->visibleInChat()->whereKey($reply->id)->exists());

        $this->assertDatabaseHas('messages', [
            'id' => $root->id,
            'reply_count' => 0,
        ]);
    }

    public function test_group_message_reaction_uses_polymorphic_type_and_is_journaled(): void
    {
        config()->set('group-chat.transport', 'polling');
        [$group, $owner] = $this->makeGroupWithMember(1);
        $message = $this->makeMessage($group, $owner);
        $reaction = \App\Models\MessageReaction::REACTIONS[0];

        $this->actingAs($owner)
            ->postJson(route('messages.reaction', $message), ['reaction_type' => $reaction])
            ->assertOk()
            ->assertJsonPath('status', 'success');

        $this->assertDatabaseHas('message_reactions', [
            'message_id' => $message->id,
            'message_type' => Message::class,
            'user_id' => $owner->id,
            'reaction_type' => $reaction,
        ]);
        $this->assertDatabaseHas('group_sync_events', [
            'group_id' => $group->id,
            'action' => 'reaction',
            'content_type' => 'message',
            'content_id' => $message->id,
        ]);

        $sync = $this->actingAs($owner)->getJson(route('groups.sync', [
            'group' => $group,
            'after_cursor' => 0,
        ]));
        $sync->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('events.0.action', 'reaction')
            ->assertJsonPath('events.0.content_type', 'message')
            ->assertJsonPath('events.0.content_id', $message->id)
            ->assertJsonPath('has_more', false);
        $this->assertGreaterThan(0, (int) $sync->json('cursor'));
    }

    public function test_non_member_cannot_consume_group_sync_journal(): void
    {
        [$group] = $this->makeGroupWithMember();
        $outsider = $this->makeUser();

        $this->actingAs($outsider)
            ->getJson(route('groups.sync', ['group' => $group, 'after_cursor' => 0]))
            ->assertForbidden();
    }

    public function test_group_sync_journal_is_ordered_and_resumable_by_cursor(): void
    {
        config()->set('group-chat.transport', 'polling');
        [$group, $owner] = $this->makeGroupWithMember(1);
        $secondClient = $this->makeUser();
        GroupUser::create([
            'group_id' => $group->id,
            'user_id' => $secondClient->id,
            'role' => 1,
            'status' => 1,
        ]);
        $publisher = app(\App\Services\GroupChat\GroupEventPublisher::class);

        foreach (['post_created', 'poll_created', 'comment_created', 'pin_updated', 'session_started', 'election_started'] as $index => $action) {
            $publisher->publish(new \App\Events\GroupFeedUpdated(
                (int) $group->id,
                $action,
                ['id' => $index + 1, 'post_id' => $action === 'post_created' ? 101 : null],
                (int) $owner->id
            ));
        }

        $first = $this->actingAs($secondClient)->getJson(route('groups.sync', [
            'group' => $group,
            'after_cursor' => 0,
            'limit' => 2,
        ]));
        $first->assertOk()
            ->assertJsonCount(2, 'events')
            ->assertJsonPath('events.0.action', 'post_created')
            ->assertJsonPath('events.1.action', 'poll_created')
            ->assertJsonPath('has_more', true);

        $second = $this->actingAs($secondClient)->getJson(route('groups.sync', [
            'group' => $group,
            'after_cursor' => $first->json('cursor'),
            'limit' => 10,
        ]));
        $second->assertOk()
            ->assertJsonCount(4, 'events')
            ->assertJsonPath('events.0.action', 'comment_created')
            ->assertJsonPath('events.3.action', 'election_started')
            ->assertJsonPath('has_more', false);
    }

    public function test_group_manager_can_admin_hide_message(): void
    {
        [$group, $owner] = $this->makeGroupWithMember(1);
        $manager = $this->makeUser();
        GroupUser::create([
            'group_id' => $group->id,
            'user_id' => $manager->id,
            'role' => 3,
            'status' => 1,
        ]);

        $message = $this->makeMessage($group, $owner);

        $response = $this->actingAs($manager)->postJson(route('groups.messages.delete', $message) . '?admin=1');

        $response->assertOk()->assertJsonFragment([
            'status' => 'success',
            'removed' => true,
        ]);

        $this->assertDatabaseHas('messages', [
            'id' => $message->id,
            'removed_by' => $manager->id,
        ]);
    }

    public function test_store_is_idempotent_for_same_client_message_id(): void
    {
        [$group, $owner] = $this->makeGroupWithMember(1);
        $clientMessageId = 'cmid-' . uniqid();

        $payload = [
            'group_id' => $group->id,
            'message' => 'retry-safe message',
            'client_message_id' => $clientMessageId,
        ];

        $firstResponse = $this->actingAs($owner)->postJson(route('groups.messages.store'), $payload);

        $firstResponse->assertOk()->assertJsonFragment([
            'status' => 'success',
        ]);

        $secondResponse = $this->actingAs($owner)->postJson(route('groups.messages.store'), $payload);

        $secondResponse->assertOk()->assertJsonFragment([
            'status' => 'success',
            'idempotent' => true,
        ]);

        $this->assertSame(1, Message::where('group_id', $group->id)
            ->where('user_id', $owner->id)
            ->where('client_message_id', $clientMessageId)
            ->count());

        $this->assertDatabaseHas('messages', [
            'group_id' => $group->id,
            'user_id' => $owner->id,
            'client_message_id' => $clientMessageId,
            'message' => 'retry-safe message',
        ]);
    }

    public function test_unread_count_includes_all_group_content_and_excludes_own_items(): void
    {
        [$group, $viewer] = $this->makeGroupWithMember(1);
        $sender = $this->makeUser();
        GroupUser::create([
            'group_id' => $group->id,
            'user_id' => $sender->id,
            'role' => 1,
            'status' => 1,
        ]);

        $this->makeMessage($group, $sender, ['message' => 'plain']);
        $this->makeMessage($group, $sender, ['message' => 'voice', 'voice_message' => 'voice.webm']);
        $this->makeMessage($group, $sender, ['message' => 'file', 'file_path' => 'uploads/file.mp3', 'file_type' => 'audio/mpeg']);
        $this->makeMessage($group, $sender, [
            'message' => 'already read',
            'read_by' => [$viewer->id => now()->toIso8601String()],
        ]);
        $this->makeMessage($group, $viewer, ['message' => 'own message']);

        Blog::create([
            'group_id' => $group->id,
            'user_id' => $sender->id,
            'category_id' => 1,
            'title' => 'Unread post',
            'content' => 'Post body',
        ]);
        Blog::create([
            'group_id' => $group->id,
            'user_id' => $viewer->id,
            'category_id' => 1,
            'title' => 'Own post',
            'content' => 'Post body',
        ]);

        Poll::create([
            'group_id' => $group->id,
            'created_by' => $sender->id,
            'question' => 'Unread poll?',
            'type' => 0,
            'main_type' => 1,
            'expires_at' => now()->addDay(),
        ]);
        Poll::create([
            'group_id' => $group->id,
            'created_by' => $viewer->id,
            'question' => 'Own poll?',
            'type' => 0,
            'main_type' => 1,
            'expires_at' => now()->addDay(),
        ]);

        $this->actingAs($viewer)
            ->getJson(route('groups.unread-count', $group))
            ->assertOk()
            ->assertJsonPath('unread.total', 5)
            ->assertJsonPath('unread.messages', 3)
            ->assertJsonPath('unread.posts', 1)
            ->assertJsonPath('unread.polls', 1);
    }

    public function test_closed_session_blocks_ordinary_member_message_mutations(): void
    {
        [$group, $member] = $this->makeGroupWithMember(1);
        $group->update(['is_open' => false]);
        $message = $this->makeMessage($group, $member);

        $this->actingAs($member)
            ->postJson(route('groups.messages.store'), ['group_id' => $group->id, 'message' => 'blocked'])
            ->assertForbidden();

        $this->actingAs($member)
            ->postJson(route('groups.messages.edit', $message), ['content' => 'blocked edit'])
            ->assertForbidden();
    }

    public function test_observer_is_read_only_even_in_open_group_and_with_session_permission(): void
    {
        [$group, $observer] = $this->makeGroupWithMember(0);
        GroupUser::where('group_id', $group->id)->where('user_id', $observer->id)
            ->update(['session_write_allowed' => true]);
        $message = $this->makeMessage($group, $observer);
        $poll = Poll::create([
            'group_id' => $group->id,
            'created_by' => $observer->id,
            'question' => 'Observer poll?',
            'is_active' => true,
            'expires_at' => now()->addDay(),
        ]);
        $option = $poll->options()->create(['text' => 'No']);

        $this->assertFalse($observer->can('participate', $group));

        $this->actingAs($observer)
            ->postJson(route('groups.messages.store'), ['group_id' => $group->id, 'message' => 'blocked'])
            ->assertForbidden()
            ->assertJsonPath('error.code', 'observer_read_only');
        $this->actingAs($observer)
            ->postJson(route('groups.messages.edit', $message), ['content' => 'blocked edit'])
            ->assertForbidden();
        $this->actingAs($observer)
            ->postJson(route('messages.reaction', $message), ['reaction_type' => \App\Models\MessageReaction::REACTIONS[0]])
            ->assertForbidden();
        $this->actingAs($observer)
            ->postJson(route('poll.vote', $poll), ['option_id' => $option->id])
            ->assertForbidden();

        $election = Election::create([
            'group_id' => $group->id,
            'starts_at' => now()->subHour(),
            'ends_at' => now()->addHour(),
            'is_closed' => false,
        ]);
        $this->actingAs($observer)
            ->postJson(route('finish.election', $election))
            ->assertForbidden();

        $group->update(['is_open' => false]);
        $this->actingAs($observer)
            ->postJson(route('groups.session-participation.request', $group), ['message' => 'let me write'])
            ->assertForbidden();
    }

    public function test_group_manager_can_temporarily_toggle_observer_and_active_roles(): void
    {
        [$group, $manager] = $this->makeGroupWithMember(3);
        $observer = $this->makeUser();
        GroupUser::create(['group_id' => $group->id, 'user_id' => $observer->id, 'role' => 0, 'status' => 1]);

        $this->actingAs($manager)
            ->postJson(route('groups.members.toggle-role', [$group, $observer]), ['duration_hours' => 2])
            ->assertOk()
            ->assertJsonPath('new_role', 1);
        $this->assertTrue($observer->fresh()->can('participate', $group));

        $this->actingAs($manager)
            ->postJson(route('groups.members.toggle-role', [$group, $observer]), ['duration_hours' => 2])
            ->assertOk()
            ->assertJsonPath('new_role', 0);
        $this->assertFalse($observer->fresh()->can('participate', $group));
        $this->assertDatabaseHas('group_user', [
            'group_id' => $group->id,
            'user_id' => $observer->id,
            'role' => 0,
            'role_override_active' => false,
            'role_override_expires_at' => null,
        ]);

        $ordinary = $this->makeUser();
        GroupUser::create(['group_id' => $group->id, 'user_id' => $ordinary->id, 'role' => 1, 'status' => 1]);
        $this->actingAs($ordinary)
            ->postJson(route('groups.members.toggle-role', [$group, $observer]), ['duration_hours' => 2])
            ->assertForbidden();
    }

    public function test_group_manager_role_override_requires_one_to_twenty_four_hours(): void
    {
        [$group, $manager] = $this->makeGroupWithMember(3);
        $observer = $this->makeUser();
        GroupUser::create(['group_id' => $group->id, 'user_id' => $observer->id, 'role' => 0, 'status' => 1]);

        $this->actingAs($manager)
            ->postJson(route('groups.members.toggle-role', [$group, $observer]), ['duration_hours' => 0])
            ->assertUnprocessable();
        $this->actingAs($manager)
            ->postJson(route('groups.members.toggle-role', [$group, $observer]), ['duration_hours' => 25])
            ->assertUnprocessable();
        $this->assertDatabaseHas('group_user', ['group_id' => $group->id, 'user_id' => $observer->id, 'role' => 0]);
    }

    public function test_system_admin_still_needs_group_level_permission_in_closed_session(): void
    {
        [$group, $member] = $this->makeGroupWithMember(1);
        $group->update(['is_open' => false]);
        $member->update(['is_admin' => true]);

        $this->actingAs($member)
            ->postJson(route('groups.messages.store'), ['group_id' => $group->id, 'message' => 'must be blocked'])
            ->assertForbidden();

        GroupUser::where('group_id', $group->id)->where('user_id', $member->id)
            ->update(['session_write_allowed' => true]);

        $this->actingAs($member)
            ->postJson(route('groups.messages.store'), ['group_id' => $group->id, 'message' => 'now allowed'])
            ->assertSuccessful();
    }

    public function test_closed_session_allows_inspector_manager_and_explicitly_permitted_member(): void
    {
        foreach ([2, 3] as $role) {
            [$group, $member] = $this->makeGroupWithMember($role);
            $group->update(['is_open' => false]);

            $this->actingAs($member)
                ->postJson(route('groups.messages.store'), ['group_id' => $group->id, 'message' => "role {$role}"])
                ->assertSuccessful();
        }

        [$group, $member] = $this->makeGroupWithMember(1);
        $group->update(['is_open' => false]);
        GroupUser::where('group_id', $group->id)->where('user_id', $member->id)
            ->update(['session_write_allowed' => true]);

        $this->actingAs($member)
            ->postJson(route('groups.messages.store'), ['group_id' => $group->id, 'message' => 'explicit permission'])
            ->assertSuccessful();
    }

    public function test_only_manager_or_inspector_can_toggle_session_and_member_permission(): void
    {
        [$group, $ordinary] = $this->makeGroupWithMember(1);
        $inspector = $this->makeUser();
        GroupUser::create(['group_id' => $group->id, 'user_id' => $inspector->id, 'role' => 2, 'status' => 1]);

        $this->actingAs($ordinary)->post(route('groups.session.toggle', $group))->assertForbidden();
        $this->actingAs($inspector)->post(route('groups.session.toggle', $group))->assertRedirect();
        $this->assertFalse((bool) $group->fresh()->is_open);

        $this->actingAs($inspector)
            ->post(route('groups.session-permissions.toggle', [$group, $ordinary]))
            ->assertRedirect();
        $this->assertTrue((bool) GroupUser::where('group_id', $group->id)
            ->where('user_id', $ordinary->id)->value('session_write_allowed'));
    }

    public function test_moderator_can_start_and_end_a_described_session(): void
    {
        [$group] = $this->makeGroupWithMember(1);
        $manager = $this->makeUser();
        GroupUser::create(['group_id' => $group->id, 'user_id' => $manager->id, 'role' => 3, 'status' => 1]);

        $this->actingAs($manager)->postJson(route('groups.session.toggle', $group), [
            'title' => 'جلسه برنامه‌ریزی محله', 'subject' => 'بودجه ماهانه',
            'agenda' => "گزارش مالی\nتصمیم‌گیری", 'starts_at' => now()->subMinute()->toIso8601String(),
        ])->assertOk()->assertJsonPath('session.status', 'active');
        $this->assertFalse((bool) $group->fresh()->is_open);
        $this->assertDatabaseHas('group_sessions', ['group_id' => $group->id, 'title' => 'جلسه برنامه‌ریزی محله', 'status' => 'active']);

        $this->actingAs($manager)->postJson(route('groups.session.toggle', $group))
            ->assertOk()->assertJsonPath('session.status', 'ended');
        $this->assertTrue((bool) $group->fresh()->is_open);
    }

    public function test_member_can_raise_hand_and_inspector_can_approve_request(): void
    {
        [$group, $member] = $this->makeGroupWithMember(1);
        $inspector = $this->makeUser();
        GroupUser::create(['group_id' => $group->id, 'user_id' => $inspector->id, 'role' => 2, 'status' => 1]);
        $group->update(['is_open' => false]);

        $this->actingAs($member)->postJson(route('groups.session-participation.request', $group), [
            'message' => 'می‌خواهم درباره دستور جلسه صحبت کنم.',
        ])->assertOk()->assertJsonPath('status', 'success');

        $this->assertDatabaseHas('group_session_participation_requests', [
            'group_id' => $group->id, 'user_id' => $member->id, 'status' => 'pending',
        ]);
        $this->assertSame(1, $inspector->notifications()->count());
        $this->assertSame('group.chat.request', $inspector->notifications()->first()->data['type']);

        $this->actingAs($member)->postJson(route('groups.session-participation.request', $group), [
            'message' => 'توضیح تکمیلی درخواست',
        ])->assertOk()->assertJsonPath('already_pending', true);
        $this->assertSame(1, $inspector->notifications()->count(), 'A repeated pending request must not spam moderators.');

        $this->actingAs($inspector)->getJson(route('groups.session-participation.index', $group))
            ->assertOk()->assertJsonPath('requests.0.user_id', $member->id);

        $this->actingAs($inspector)->postJson(route('groups.session-participation.bulk', $group), [
            'user_ids' => [$member->id], 'action' => 'grant',
        ])->assertOk();

        $this->assertDatabaseHas('group_user', [
            'group_id' => $group->id, 'user_id' => $member->id, 'session_write_allowed' => true,
        ]);
        $this->assertDatabaseHas('group_session_participation_requests', [
            'group_id' => $group->id, 'user_id' => $member->id, 'status' => 'approved',
        ]);
    }

    public function test_ordinary_member_cannot_manage_session_requests(): void
    {
        [$group, $member] = $this->makeGroupWithMember(1);

        $this->actingAs($member)->getJson(route('groups.session-participation.index', $group))->assertForbidden();
        $this->actingAs($member)->postJson(route('groups.session-participation.bulk', $group), [
            'user_ids' => [$member->id], 'action' => 'grant',
        ])->assertForbidden();
    }

    public function test_only_group_moderators_can_pin_every_feed_content_type(): void
    {
        [$group, $member] = $this->makeGroupWithMember(1);
        $inspector = $this->makeUser();
        GroupUser::create(['group_id' => $group->id, 'user_id' => $inspector->id, 'role' => 2, 'status' => 1]);
        $message = $this->makeMessage($group, $member);
        $post = Blog::create(['group_id' => $group->id, 'user_id' => $member->id, 'title' => 'Pinned post', 'content' => '<p>Post body</p>', 'category_id' => 1]);
        $poll = Poll::create(['group_id' => $group->id, 'created_by' => $member->id, 'question' => 'Pinned election?', 'main_type' => 0, 'expires_at' => now()->addDay()]);

        $this->actingAs($member)->postJson(route('groups.pins.store', $group), [
            'content_type' => 'message', 'content_id' => $message->id,
        ])->assertForbidden();

        foreach ([['message', $message->id], ['post', $post->id], ['poll', $poll->id]] as [$type, $id]) {
            $this->actingAs($inspector)->postJson(route('groups.pins.store', $group), [
                'content_type' => $type, 'content_id' => $id,
            ])->assertOk()->assertJsonPath('pin.content_type', $type);
        }

        $this->actingAs($member)->getJson(route('groups.pins.index', $group))
            ->assertOk()->assertJsonCount(3, 'pins')
            ->assertJsonFragment(['label' => 'انتخابات']);

        $this->actingAs($inspector)->deleteJson(route('groups.pins.destroy', $group), [
            'content_type' => 'post', 'content_id' => $post->id,
        ])->assertOk()->assertJsonPath('pinned', false);
        $this->assertDatabaseMissing('pinned_messages', ['content_type' => Blog::class, 'content_id' => $post->id]);
    }

    public function test_pin_cannot_reference_content_from_another_group(): void
    {
        [$group, $manager] = $this->makeGroupWithMember(3);
        [$otherGroup, $otherMember] = $this->makeGroupWithMember(1);
        $message = $this->makeMessage($otherGroup, $otherMember);

        $this->actingAs($manager)->postJson(route('groups.pins.store', $group), [
            'content_type' => 'message', 'content_id' => $message->id,
        ])->assertNotFound();
    }

    private function makeUser(): User
    {
        return User::create([
            'first_name' => 'Test',
            'last_name' => 'User',
            'email' => 'message-authorization-' . (++self::$userSequence) . '@example.test',
            'password' => bcrypt('password123'),
        ]);
    }

    private function makeGroupWithMember(int $role = 1): array
    {
        $group = Group::create([
            'group_type' => 'test',
            'name' => 'Test Group ' . fake()->unique()->word(),
            'is_open' => 1,
        ]);

        $user = $this->makeUser();

        GroupUser::create([
            'group_id' => $group->id,
            'user_id' => $user->id,
            'role' => $role,
            'status' => 1,
        ]);

        return [$group, $user];
    }

    private function makeMessage(Group $group, User $user, array $attributes = []): Message
    {
        return Message::create(array_merge([
            'group_id' => $group->id,
            'user_id' => $user->id,
            'message' => 'original message',
        ], $attributes));
    }
}
