<?php

namespace Tests\Feature;

use App\Models\ChatRequest;
use App\Models\PrivateConversation;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ChatRequestFlowTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        config(['broadcasting.default' => 'null']);

        if (! Schema::hasTable('private_conversations')) {
            Artisan::call('migrate', [
                '--path' => 'database/migrations/2026_05_27_230000_create_private_conversations_tables.php',
                '--force' => true,
            ]);
        }

        if (! Schema::hasTable('notifications')) {
            Schema::create('notifications', function (\Illuminate\Database\Schema\Blueprint $table) {
                $table->uuid('id')->primary();
                $table->string('type');
                $table->morphs('notifiable');
                $table->text('data');
                $table->timestamp('read_at')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('chat_requests')) {
            Schema::create('chat_requests', function (\Illuminate\Database\Schema\Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('sender_id');
                $table->unsignedBigInteger('receiver_id');
                $table->enum('status', ['pending', 'accepted', 'rejected'])->default('pending');
                $table->text('message')->nullable();
                $table->unsignedBigInteger('private_conversation_id')->nullable();
                $table->timestamps();
            });
        } else {
            $idColumn = DB::selectOne("SHOW COLUMNS FROM chat_requests LIKE 'id'");
            $extra = strtolower((string) ($idColumn->Extra ?? ''));

            if (strpos($extra, 'auto_increment') === false) {
                DB::statement('ALTER TABLE chat_requests MODIFY id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY');
            }

            if (! Schema::hasColumn('chat_requests', 'message')) {
                Schema::table('chat_requests', function (\Illuminate\Database\Schema\Blueprint $table) {
                    $table->text('message')->nullable();
                });
            }

            if (! Schema::hasColumn('chat_requests', 'private_conversation_id')) {
                Artisan::call('migrate', [
                    '--path' => 'database/migrations/2026_05_27_231000_add_private_conversation_id_to_chat_requests_table.php',
                    '--force' => true,
                ]);
            }

            if (Schema::hasColumn('chat_requests', 'group_id') || Schema::hasColumn('chat_requests', 'request_to_group')) {
                Artisan::call('migrate', [
                    '--path' => 'database/migrations/2026_06_21_000002_decouple_chat_requests_from_groups.php',
                    '--force' => true,
                ]);
            }
        }
    }

    public function test_user_can_send_chat_request(): void
    {
        $sender = $this->makeUser('sender1@example.com');
        $receiver = $this->makeUser('receiver1@example.com');

        $response = $this->actingAs($sender)->post(route('chat-requests.send', $receiver), [
            'description' => 'test chat request',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('chat_requests', [
            'sender_id' => $sender->id,
            'receiver_id' => $receiver->id,
            'status' => 'pending',
        ]);
    }

    public function test_rejected_request_can_be_retried(): void
    {
        $sender = $this->makeUser('sender2@example.com');
        $receiver = $this->makeUser('receiver2@example.com');

        ChatRequest::create([
            'sender_id' => $sender->id,
            'receiver_id' => $receiver->id,
            'status' => 'rejected',
            'message' => 'old message',
        ]);

        $response = $this->actingAs($sender)->post(route('chat-requests.send', $receiver), [
            'description' => 'new message',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('chat_requests', [
            'sender_id' => $sender->id,
            'receiver_id' => $receiver->id,
            'status' => 'pending',
            'message' => 'new message',
        ]);
    }

    public function test_accept_reuses_existing_private_conversation(): void
    {
        $sender = $this->makeUser('sender3@example.com');
        $receiver = $this->makeUser('receiver3@example.com');

        $conversation = PrivateConversation::create(['status' => 'active']);
        $conversation->users()->syncWithoutDetaching([$sender->id, $receiver->id]);

        ChatRequest::create([
            'sender_id' => $sender->id,
            'receiver_id' => $receiver->id,
            'status' => 'accepted',
            'private_conversation_id' => $conversation->id,
            'message' => 'accepted',
        ]);

        $pending = ChatRequest::create([
            'sender_id' => $receiver->id,
            'receiver_id' => $sender->id,
            'status' => 'pending',
            'message' => 'pending',
        ]);

        $response = $this->actingAs($sender)->post(route('chat-requests.accept', $pending));

        $response->assertRedirect(route('private-chats.show', $conversation->id));
        $this->assertDatabaseHas('chat_requests', [
            'id' => $pending->id,
            'status' => 'accepted',
            'private_conversation_id' => $conversation->id,
        ]);
    }

    public function test_accept_creates_private_conversation_without_group(): void
    {
        $sender = $this->makeUser('sender5@example.com');
        $receiver = $this->makeUser('receiver5@example.com');

        $request = ChatRequest::create([
            'sender_id' => $sender->id,
            'receiver_id' => $receiver->id,
            'status' => 'pending',
            'message' => 'pending',
        ]);

        $response = $this->actingAs($receiver)->post(route('chat-requests.accept', $request));

        $request->refresh();
        $this->assertNotNull($request->private_conversation_id);
        $response->assertRedirect(route('private-chats.show', $request->private_conversation_id));
        $this->assertDatabaseHas('private_conversation_user', [
            'private_conversation_id' => $request->private_conversation_id,
            'user_id' => $sender->id,
        ]);
        $this->assertDatabaseHas('private_conversation_user', [
            'private_conversation_id' => $request->private_conversation_id,
            'user_id' => $receiver->id,
        ]);
    }

    public function test_non_receiver_cannot_accept_request(): void
    {
        $sender = $this->makeUser('sender4@example.com');
        $receiver = $this->makeUser('receiver4@example.com');
        $other = $this->makeUser('other4@example.com');

        $request = ChatRequest::create([
            'sender_id' => $sender->id,
            'receiver_id' => $receiver->id,
            'status' => 'pending',
            'message' => 'pending',
        ]);

        $response = $this->actingAs($other)->post(route('chat-requests.accept', $request));

        $response->assertRedirect();
        $this->assertDatabaseHas('chat_requests', [
            'id' => $request->id,
            'status' => 'pending',
            'private_conversation_id' => null,
        ]);
    }

    public function test_accepted_legacy_request_is_redirected_to_private_conversation(): void
    {
        $sender = $this->makeUser('sender6@example.com');
        $receiver = $this->makeUser('receiver6@example.com');

        $request = ChatRequest::create([
            'sender_id' => $sender->id,
            'receiver_id' => $receiver->id,
            'status' => 'accepted',
            'message' => 'legacy accepted request',
        ]);

        $response = $this->actingAs($sender)->post(route('chat-requests.send', $receiver), [
            'description' => 'retry request',
        ]);

        $request->refresh();

        $this->assertNotNull($request->private_conversation_id);
        $response->assertRedirect(route('private-chats.show', $request->private_conversation_id));
    }

    private function makeUser(string $email): User
    {
        [$local, $domain] = array_pad(explode('@', $email, 2), 2, 'example.com');
        $uniqueEmail = sprintf('%s+%s@%s', $local, uniqid('t', true), $domain);

        return User::create([
            'email' => $uniqueEmail,
            'password' => Hash::make('password123'),
            'first_name' => 'Test',
            'last_name' => 'User',
        ]);
    }
}
