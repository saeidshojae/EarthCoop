<?php

namespace Tests\Feature\Api\V1;

use App\Models\Conversation;
use App\Models\User;
use App\Services\NajmHoda\Api\NajmHodaConversationService;
use App\Services\NajmHoda\Runtime\NajmHodaExecutionService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Mockery;
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

    public function test_v1_conversation_routes_require_native_bearer_authentication(): void
    {
        $this->getJson('/api/v1/najm-hoda/conversations')
            ->assertStatus(401)
            ->assertJsonPath('error.code', 'unauthenticated');
    }

    public function test_v1_create_list_and_show_are_owner_scoped_and_use_m1_envelope(): void
    {
        [$actor, $token, $deviceId] = $this->nativeSession();
        $other = User::factory()->create();
        $foreign = Conversation::create([
            'user_id' => $other->id,
            'title' => 'Foreign',
            'agent_type' => 'steward',
            'status' => 'active',
        ]);

        $created = $this->bearer($token, $deviceId)
            ->postJson('/api/v1/najm-hoda/conversations', ['agent_type' => 'steward'])
            ->assertCreated()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.user_id', $actor->id)
            ->assertJsonPath('data.agent_type', 'steward')
            ->assertJsonStructure(['request_id', 'meta' => ['api_version']]);

        $conversationId = (int) $created->json('data.id');

        $this->bearer($token, $deviceId)
            ->getJson('/api/v1/najm-hoda/conversations')
            ->assertOk()
            ->assertJsonPath('data.0.id', $conversationId);

        $this->bearer($token, $deviceId)
            ->getJson('/api/v1/najm-hoda/conversations/'.$conversationId)
            ->assertOk()
            ->assertJsonPath('data.id', $conversationId);

        $this->bearer($token, $deviceId)
            ->getJson('/api/v1/najm-hoda/conversations/'.$foreign->id)
            ->assertStatus(404)
            ->assertJsonPath('error.code', 'not_found');
    }

    public function test_v1_message_execution_uses_server_actor_and_does_not_forward_forged_authority_context(): void
    {
        [$actor, $token, $deviceId] = $this->nativeSession();
        $conversation = Conversation::create([
            'user_id' => $actor->id,
            'title' => 'Safe context',
            'agent_type' => 'steward',
            'status' => 'active',
        ]);

        $execution = Mockery::mock(NajmHodaExecutionService::class);
        $execution->shouldReceive('executeChat')
            ->once()
            ->withArgs(function ($orchestrator, string $message, array $context) use ($actor, $conversation): bool {
                $this->assertSame('hello', $message);
                $this->assertSame($actor->id, $context['user_id'] ?? null);
                $this->assertSame($conversation->id, data_get($context, 'conversation.id'));
                $this->assertArrayNotHasKey('runtime_action_authority', $context);
                $this->assertArrayNotHasKey('trusted_apply_request', $context);
                $this->assertArrayNotHasKey('actor_id', $context);

                return true;
            })
            ->andReturn([
                'success' => true,
                'message' => 'safe answer',
                'agent' => 'steward',
                'agent_name' => 'نجم هدا',
                'agent_icon' => '🤖',
                'suggestions' => [],
                'response_time_ms' => 1,
                'request_id' => 'runtime-request',
            ]);
        $this->app->instance(NajmHodaExecutionService::class, $execution);

        $this->bearer($token, $deviceId)
            ->postJson('/api/v1/najm-hoda/conversations/'.$conversation->id.'/messages', [
                'message' => 'hello',
                'context' => [
                    'user_id' => 999999,
                    'actor_id' => 999999,
                    'trusted_apply_request' => true,
                    'runtime_action_authority' => ['allow_apply' => true],
                    'page' => ['type' => 'forged'],
                ],
            ])
            ->assertOk()
            ->assertJsonPath('data.message', 'safe answer')
            ->assertJsonPath('data.conversation_id', $conversation->id);

        $this->assertDatabaseHas('conversation_messages', [
            'conversation_id' => $conversation->id,
            'role' => 'user',
            'content' => 'hello',
        ]);
        $this->assertDatabaseHas('conversation_messages', [
            'conversation_id' => $conversation->id,
            'role' => 'assistant',
            'content' => 'safe answer',
        ]);
    }

    private function nativeSession(): array
    {
        $password = 'secret-password';
        $user = User::factory()->create([
            'email' => 'hoda-'.bin2hex(random_bytes(4)).'@example.test',
            'password' => Hash::make($password),
            'is_system' => false,
            'status' => 'active',
            'email_verified_at' => now(),
        ]);

        $login = $this->postJson('/api/v1/auth/session', [
            'email' => $user->email,
            'password' => $password,
            'platform' => 'android',
            'app_version' => '1.0.0',
            'locale' => 'fa',
            'timezone' => 'Asia/Tehran',
            'push_capable' => false,
        ])->assertCreated();

        return [$user, (string) $login->json('data.token'), (string) $login->json('data.device.id')];
    }

    private function bearer(string $token, string $deviceId): self
    {
        Auth::forgetGuards();

        return $this->withToken($token)->withHeader('X-Device-ID', $deviceId);
    }
}
