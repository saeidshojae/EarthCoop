<?php

namespace Tests\Feature\GroupChat;

use App\Models\Group;
use App\Models\GroupUser;
use App\Models\Blog;
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

        $this->assertDatabaseMissing('messages', [
            'id' => $reply->id,
        ]);

        $this->assertDatabaseHas('messages', [
            'id' => $root->id,
            'reply_count' => 0,
        ]);
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

    private function makeUser(): User
    {
        return User::create([
            'first_name' => 'Test',
            'last_name' => 'User',
            'email' => fake()->unique()->safeEmail(),
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
