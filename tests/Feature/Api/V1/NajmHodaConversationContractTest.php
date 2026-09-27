<?php

namespace Tests\Feature\Api\V1;

use App\Models\Conversation;
use App\Models\User;
use App\Services\NajmHoda\Api\NajmHodaConversationService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class NajmHodaConversationContractTest extends TestCase
{
    use DatabaseTransactions;

    public function test_service_lists_only_owned_conversations_and_caps_page_size(): void
    {
        $actor = User::factory()->create();
        $other = User::factory()->create();

        Conversation::create(['user_id' => $actor->id, 'title' => 'Mine', 'agent_type' => 'steward', 'status' => 'active']);
        Conversation::create(['user_id' => $other->id, 'title' => 'Foreign', 'agent_type' => 'steward', 'status' => 'active']);

        $service = app(NajmHodaConversationService::class);
        $result = $service->list($actor, [], 500);

        $this->assertSame(50, $result->perPage());
        $this->assertSame(1, $result->total());
        $this->assertSame('Mine', $result->items()[0]->title);
    }

    public function test_service_get_conceals_foreign_conversation_as_not_found(): void
    {
        $actor = User::factory()->create();
        $other = User::factory()->create();
        $foreign = Conversation::create(['user_id' => $other->id, 'title' => 'Foreign', 'agent_type' => 'steward', 'status' => 'active']);

        $this->expectException(ModelNotFoundException::class);
        app(NajmHodaConversationService::class)->get($actor, $foreign->id);
    }

    public function test_service_start_or_get_and_message_append_preserve_owner_and_metadata(): void
    {
        $actor = User::factory()->create();
        $this->actingAs($actor);
        $service = app(NajmHodaConversationService::class);

        $conversation = $service->startOrGet($actor, null, 'steward');
        $userMessage = $service->appendUserMessage($conversation, 'hello');
        $assistantMessage = $service->appendAssistantMessage($conversation, 'answer', 'steward');

        $this->assertSame($actor->id, $conversation->user_id);
        $this->assertSame('steward', $conversation->agent_type);
        $this->assertSame('user', $userMessage->role);
        $this->assertSame('assistant', $assistantMessage->role);
        $this->assertSame('steward', $assistantMessage->metadata['agent']);
        $this->assertSame($conversation->id, $service->startOrGet($actor, $conversation->id)->id);
    }

    public function test_service_filters_status_and_agent_without_crossing_owner_scope(): void
    {
        $actor = User::factory()->create();
        Conversation::create(['user_id' => $actor->id, 'title' => 'Active steward', 'agent_type' => 'steward', 'status' => 'active']);
        Conversation::create(['user_id' => $actor->id, 'title' => 'Archived guide', 'agent_type' => 'guide', 'status' => 'archived']);

        $result = app(NajmHodaConversationService::class)->list($actor, ['status' => 'archived', 'agent' => 'guide'], 20);

        $this->assertSame(1, $result->total());
        $this->assertSame('Archived guide', $result->items()[0]->title);
    }
}
