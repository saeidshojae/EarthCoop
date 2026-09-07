<?php

namespace Tests\Feature;

use App\Events\PrivateMessagesRead;
use App\Http\Middleware\UpdateLastSeen;
use App\Models\PrivateConversation;
use App\Models\PrivateMessage;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Event;
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

    public function test_marking_messages_read_dispatches_private_read_receipt_event(): void
    {
        Event::fake([PrivateMessagesRead::class]);
        [$sender, $receiver, $conversation] = $this->makeConversation();

        $incoming = PrivateMessage::create([
            'private_conversation_id' => $conversation->id,
            'sender_id' => $sender->id,
            'message' => 'برای رسید خواندن',
        ]);

        $this->actingAs($receiver)
            ->getJson(route('private-chats.messages', [
                'conversation' => $conversation->id,
                'after_id' => 0,
            ]))
            ->assertOk();

        Event::assertDispatched(PrivateMessagesRead::class, function ($event) use ($conversation, $incoming, $receiver) {
            return (int) $event->conversation->id === (int) $conversation->id
                && $event->messageIds === [$incoming->id]
                && (int) $event->readerId === (int) $receiver->id;
        });
    }

    public function test_conversation_info_exposes_latest_read_outgoing_message_for_polling_fallback(): void
    {
        [$sender, $receiver, $conversation] = $this->makeConversation();

        $readMessage = PrivateMessage::create([
            'private_conversation_id' => $conversation->id,
            'sender_id' => $sender->id,
            'message' => 'خوانده شده',
        ]);
        $readMessage->forceFill(['read_at' => now()])->save();

        PrivateMessage::create([
            'private_conversation_id' => $conversation->id,
            'sender_id' => $sender->id,
            'message' => 'هنوز خوانده نشده',
        ]);

        $this->actingAs($sender)
            ->getJson(route('private-chats.info', $conversation->id))
            ->assertOk()
            ->assertJsonPath('conversation.last_read_outgoing_message_id', $readMessage->id);
    }

    public function test_conversation_list_counts_only_unread_incoming_messages_and_clears_after_view(): void
    {
        [$sender, $receiver, $conversation] = $this->makeConversation();

        PrivateMessage::create([
            'private_conversation_id' => $conversation->id,
            'sender_id' => $sender->id,
            'message' => 'خوانده نشده یک',
        ]);
        PrivateMessage::create([
            'private_conversation_id' => $conversation->id,
            'sender_id' => $sender->id,
            'message' => 'خوانده نشده دو',
        ]);

        $alreadyRead = PrivateMessage::create([
            'private_conversation_id' => $conversation->id,
            'sender_id' => $sender->id,
            'message' => 'قبلاً خوانده شده',
        ]);
        $alreadyRead->forceFill(['read_at' => now()])->save();

        PrivateMessage::create([
            'private_conversation_id' => $conversation->id,
            'sender_id' => $receiver->id,
            'message' => 'پیام خود گیرنده',
        ]);

        $this->actingAs($receiver)
            ->get(route('private-chats.index'))
            ->assertOk()
            ->assertViewHas('conversations', function ($conversations) use ($conversation) {
                $row = $conversations->firstWhere('id', $conversation->id);
                return $row && (int) $row->unread_count === 2;
            });

        $this->actingAs($receiver)
            ->get(route('private-chats.show', $conversation->id))
            ->assertOk();

        $this->actingAs($receiver)
            ->get(route('private-chats.index'))
            ->assertOk()
            ->assertViewHas('conversations', function ($conversations) use ($conversation) {
                $row = $conversations->firstWhere('id', $conversation->id);
                return $row && (int) $row->unread_count === 0;
            });
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
