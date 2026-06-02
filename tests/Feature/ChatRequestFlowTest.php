<?php

namespace Tests\Feature;

use App\Models\ChatRequest;
use App\Models\PrivateConversation;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ChatRequestFlowTest extends TestCase
{
    use DatabaseTransactions;

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
            'group_id' => null,
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
        $this->assertNull($request->group_id);
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
            'group_id' => null,
            'private_conversation_id' => null,
        ]);
    }

    private function makeUser(string $email): User
    {
        return User::create([
            'email' => $email,
            'password' => Hash::make('password123'),
            'first_name' => 'Test',
            'last_name' => 'User',
        ]);
    }
}
