<?php

namespace Tests\Feature\NajmHoda;

use App\Models\Conversation;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ConversationOwnershipSecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_write_to_own_conversation(): void
    {
        $user = User::factory()->create();
        $conversation = Conversation::create([
            'user_id' => $user->id,
            'title' => 'Own conversation',
            'agent_type' => 'steward',
            'status' => 'active',
        ]);

        $this->actingAs($user);

        $message = $conversation->messages()->create([
            'role' => 'user',
            'content' => 'سلام',
        ]);

        $this->assertDatabaseHas('conversation_messages', [
            'id' => $message->id,
            'conversation_id' => $conversation->id,
            'role' => 'user',
        ]);
    }

    public function test_authenticated_user_cannot_write_to_another_users_conversation(): void
    {
        $owner = User::factory()->create();
        $attacker = User::factory()->create();
        $conversation = Conversation::create([
            'user_id' => $owner->id,
            'title' => 'Private conversation',
            'agent_type' => 'steward',
            'status' => 'active',
        ]);

        $this->actingAs($attacker);

        $this->expectException(AuthorizationException::class);

        $conversation->messages()->create([
            'role' => 'user',
            'content' => 'unauthorized write',
        ]);
    }
}
