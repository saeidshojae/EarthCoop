<?php

namespace Tests\Feature\GroupChat;

use App\Models\Group;
use App\Models\GroupUser;
use App\Models\Message;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class VoiceMessageFlowTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        $this->bootstrapGroupChatSchema();
        config(['broadcasting.default' => 'null']);
    }

    public function test_member_can_send_voice_message_only(): void
    {
        Storage::fake('local');

        [$group, $member] = $this->makeGroupWithMember(1);

        $response = $this->actingAs($member)->post(route('groups.messages.store'), [
            'group_id' => $group->id,
            'voice_message' => UploadedFile::fake()->create('sample.webm', 128, 'audio/webm'),
            'message' => '',
        ], [
            'Accept' => 'application/json',
        ]);

        $response->assertOk()->assertJsonPath('status', 'success');

        $saved = Message::query()->latest('id')->first();
        $this->assertNotNull($saved);
        $this->assertNotNull($saved->voice_message);
        $this->assertSame('Voice message', $saved->message);
        $this->assertSame($group->id, (int) $saved->group_id);
        $this->assertSame($member->id, (int) $saved->user_id);
        Storage::disk('local')->assertExists($saved->voice_message);

        $payload = $response->json('message');
        $this->assertIsArray($payload);
        $this->assertNotEmpty($payload['voice_message'] ?? null);
        $this->assertStringContainsString('/messages/' . $saved->id . '/voice', (string) $payload['voice_message']);
    }

    public function test_non_member_cannot_send_voice_message(): void
    {
        [$group, $member] = $this->makeGroupWithMember(1);
        $otherUser = $this->makeUser();

        $response = $this->actingAs($otherUser)->post(route('groups.messages.store'), [
            'group_id' => $group->id,
            'voice_message' => UploadedFile::fake()->create('sample.webm', 128, 'audio/webm'),
        ], [
            'Accept' => 'application/json',
        ]);

        $response->assertStatus(403);
    }

    public function test_invalid_voice_file_is_rejected(): void
    {
        [$group, $member] = $this->makeGroupWithMember(1);

        $response = $this->actingAs($member)->post(route('groups.messages.store'), [
            'group_id' => $group->id,
            'voice_message' => UploadedFile::fake()->create('not-audio.txt', 10, 'text/plain'),
        ], [
            'Accept' => 'application/json',
        ]);

        $response->assertStatus(422);
    }

    public function test_voice_message_store_is_idempotent_with_client_message_id(): void
    {
        Storage::fake('local');

        [$group, $member] = $this->makeGroupWithMember(1);
        $clientMessageId = 'voice-cmid-' . uniqid();

        $first = $this->actingAs($member)->post(route('groups.messages.store'), [
            'group_id' => $group->id,
            'voice_message' => UploadedFile::fake()->create('first.webm', 128, 'audio/webm'),
            'client_message_id' => $clientMessageId,
        ], [
            'Accept' => 'application/json',
        ]);

        $first->assertOk()->assertJsonPath('status', 'success');

        $second = $this->actingAs($member)->post(route('groups.messages.store'), [
            'group_id' => $group->id,
            'voice_message' => UploadedFile::fake()->create('second.webm', 128, 'audio/webm'),
            'client_message_id' => $clientMessageId,
        ], [
            'Accept' => 'application/json',
        ]);

        $second->assertOk()->assertJsonPath('status', 'success');
        $second->assertJsonPath('idempotent', true);

        $count = Message::query()
            ->where('group_id', $group->id)
            ->where('user_id', $member->id)
            ->where('client_message_id', $clientMessageId)
            ->count();

        $this->assertSame(1, $count);
    }

    public function test_group_member_can_stream_voice_message(): void
    {
        Storage::fake('local');

        [$group, $sender] = $this->makeGroupWithMember(1);
        $receiver = $this->makeUser();

        GroupUser::create([
            'group_id' => $group->id,
            'user_id' => $receiver->id,
            'role' => 1,
            'status' => 1,
        ]);

        $sendResponse = $this->actingAs($sender)->post(route('groups.messages.store'), [
            'group_id' => $group->id,
            'voice_message' => UploadedFile::fake()->create('stream.webm', 128, 'audio/webm'),
        ], [
            'Accept' => 'application/json',
        ]);

        $sendResponse->assertOk();

        $messageId = (int) $sendResponse->json('message.id');
        $response = $this->actingAs($receiver)->get(route('groups.messages.voice', ['message' => $messageId]));

        $response->assertOk();
        $response->assertHeader('Content-Type');
        $this->assertStringContainsString('audio/', (string) $response->headers->get('Content-Type'));
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

    private function makeUser(): User
    {
        return User::create([
            'first_name' => 'Voice',
            'last_name' => 'Tester',
            'email' => fake()->unique()->safeEmail(),
            'password' => bcrypt('password123'),
        ]);
    }

    private function makeGroupWithMember(int $role = 1): array
    {
        $group = Group::create([
            'group_type' => 'test',
            'name' => 'Voice Test Group ' . fake()->unique()->word(),
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
}
