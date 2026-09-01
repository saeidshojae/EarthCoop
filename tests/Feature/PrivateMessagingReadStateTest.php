<?php

namespace Tests\Feature;

use App\Http\Middleware\UpdateLastSeen;
use App\Models\PrivateConversation;
use App\Models\PrivateMessage;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class PrivateMessagingReadStateTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(UpdateLastSeen::class);
        $this->withoutVite();

        if (! Schema::hasTable('private_conversations')) {
            Schema::create('private_conversations', function (Blueprint $table) {
                $table->id();
                $table->string('status')->default('active');
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('private_conversation_user')) {
            Schema::create('private_conversation_user', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('private_conversation_id');
                $table->unsignedBigInteger('user_id');
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('private_messages')) {
            Schema::create('private_messages', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('private_conversation_id');
                $table->unsignedBigInteger('sender_id');
                $table->text('message');
                $table->timestamp('read_at')->nullable();
                $table->timestamps();
            });
        }
    }

    public function test_private_messages_have_persisted_read_state_contract(): void
    {
        $this->assertTrue(
            Schema::hasColumn('private_messages', 'read_at'),
            'Private messages must persist a nullable read_at timestamp.'
        );

        $message = new PrivateMessage();
        $casts = $message->getCasts();

        $this->assertArrayHasKey('read_at', $casts);
        $this->assertSame('datetime', $casts['read_at']);
    }

    public function test_opening_conversation_marks_only_incoming_messages_read(): void
    {
        [$sender, $receiver, $conversation] = $this->makeConversation();

        $incoming = PrivateMessage::create([
            'private_conversation_id' => $conversation->id,
            'sender_id' => $sender->id,
            'message' => 'سلام از فرستنده',
        ]);

        $own = PrivateMessage::create([
            'private_conversation_id' => $conversation->id,
            'sender_id' => $receiver->id,
            'message' => 'پیام خود گیرنده',
        ]);

        $this->actingAs($receiver)
            ->get(route('private-chats.show', $conversation->id))
            ->assertOk();

        $this->assertNotNull($incoming->fresh()->read_at);
        $this->assertNull($own->fresh()->read_at);
    }

    public function test_non_participant_cannot_open_conversation_to_mutate_read_state(): void
    {
        [$sender, $receiver, $conversation] = $this->makeConversation();
        $outsider = $this->makeUser('outsider');

        $incoming = PrivateMessage::create([
            'private_conversation_id' => $conversation->id,
            'sender_id' => $sender->id,
            'message' => 'نباید توسط فرد بیرونی خوانده شود',
        ]);

        $this->actingAs($outsider)
            ->get(route('private-chats.show', $conversation->id))
            ->assertForbidden();

        $this->assertNull($incoming->fresh()->read_at);
    }

    public function test_active_message_fetch_marks_received_message_read_and_exposes_read_payload(): void
    {
        [$sender, $receiver, $conversation] = $this->makeConversation();

        $incoming = PrivateMessage::create([
            'private_conversation_id' => $conversation->id,
            'sender_id' => $sender->id,
            'message' => 'پیام تازه',
        ]);

        $response = $this->actingAs($receiver)
            ->getJson(route('private-chats.messages', [
                'conversation' => $conversation->id,
                'after_id' => 0,
            ]));

        $response->assertOk()
            ->assertJsonPath('messages.0.id', $incoming->id)
            ->assertJsonPath('messages.0.is_read', true);

        $this->assertNotNull($incoming->fresh()->read_at);
    }

    private function makeConversation(): array
    {
        $sender = $this->makeUser('sender');
        $receiver = $this->makeUser('receiver');

        $conversation = PrivateConversation::create(['status' => 'active']);
        $conversation->users()->attach([$sender->id, $receiver->id]);

        return [$sender, $receiver, $conversation];
    }

    private function makeUser(string $prefix): User
    {
        return User::forceCreate([
            'first_name' => ucfirst($prefix),
            'last_name' => 'User',
            'email' => $prefix . '+' . uniqid() . '@example.com',
            'password' => bcrypt('password'),
        ]);
    }
}
