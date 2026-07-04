<?php

namespace Tests\Feature\GroupChat;

use App\Models\Group;
use App\Models\GroupUser;
use App\Models\Message;
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