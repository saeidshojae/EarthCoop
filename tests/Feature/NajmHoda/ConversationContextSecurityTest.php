<?php

namespace Tests\Feature\NajmHoda;

use App\Models\Conversation;
use App\Models\User;
use App\Services\NajmHoda\NajmHodaOrchestrator;
use App\Services\NajmHoda\Runtime\NajmHodaExecutionService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class ConversationContextSecurityTest extends TestCase
{
    use DatabaseTransactions;

    public function test_owned_conversation_history_is_role_preserved_and_current_message_is_not_duplicated(): void
    {
        $user = $this->makeUser('owner');
        $conversation = Conversation::create([
            'user_id' => $user->id,
            'title' => 'Continuity',
            'agent_type' => 'steward',
            'status' => 'active',
        ]);

        $conversation->messages()->createMany([
            ['role' => 'user', 'content' => 'first question'],
            ['role' => 'assistant', 'content' => 'first answer'],
            ['role' => 'user', 'content' => 'current question'],
        ]);

        $orchestrator = $this->createMock(NajmHodaOrchestrator::class);
        $orchestrator->expects($this->once())
            ->method('route')
            ->with(
                'current question',
                $this->callback(function (array $context) use ($conversation): bool {
                    $this->assertSame($conversation->id, $context['conversation']['id']);
                    $this->assertSame('steward', $context['conversation']['agent_type']);
                    $this->assertSame('active', $context['conversation']['status']);
                    $this->assertSame([
                        ['role' => 'user', 'content' => 'first question'],
                        ['role' => 'assistant', 'content' => 'first answer'],
                    ], $context['conversation_history']);

                    return true;
                })
            )
            ->willReturn([
                'success' => true,
                'message' => 'ok',
                'agent' => 'steward',
            ]);

        $result = app(NajmHodaExecutionService::class)->executeChat(
            $orchestrator,
            'current question',
            [
                'user_id' => $user->id,
                'conversation' => $conversation,
            ]
        );

        $this->assertTrue($result['success']);
    }

    public function test_foreign_conversation_object_is_dropped_from_model_context(): void
    {
        $owner = $this->makeUser('owner');
        $viewer = $this->makeUser('viewer');
        $conversation = Conversation::create([
            'user_id' => $owner->id,
            'title' => 'Private conversation',
            'agent_type' => 'steward',
            'status' => 'active',
        ]);
        $conversation->messages()->create([
            'role' => 'user',
            'content' => 'private history',
        ]);

        $orchestrator = $this->createMock(NajmHodaOrchestrator::class);
        $orchestrator->expects($this->once())
            ->method('route')
            ->with(
                'hello',
                $this->callback(function (array $context): bool {
                    $this->assertArrayNotHasKey('conversation', $context);
                    $this->assertArrayNotHasKey('conversation_history', $context);

                    return true;
                })
            )
            ->willReturn([
                'success' => true,
                'message' => 'ok',
                'agent' => 'steward',
            ]);

        $result = app(NajmHodaExecutionService::class)->executeChat(
            $orchestrator,
            'hello',
            [
                'user_id' => $viewer->id,
                'conversation' => $conversation,
            ]
        );

        $this->assertTrue($result['success']);
    }

    public function test_history_count_is_bounded_to_configured_limit(): void
    {
        config(['najm-hoda.conversation_history_messages' => 3]);

        $user = $this->makeUser('bounded');
        $conversation = Conversation::create([
            'user_id' => $user->id,
            'title' => 'Bounded history',
            'agent_type' => 'steward',
            'status' => 'active',
        ]);

        foreach (range(1, 6) as $index) {
            $conversation->messages()->create([
                'role' => $index % 2 === 0 ? 'assistant' : 'user',
                'content' => 'message-' . $index,
            ]);
        }

        $orchestrator = $this->createMock(NajmHodaOrchestrator::class);
        $orchestrator->expects($this->once())
            ->method('route')
            ->with(
                'new question',
                $this->callback(function (array $context): bool {
                    $this->assertSame([
                        ['role' => 'assistant', 'content' => 'message-4'],
                        ['role' => 'user', 'content' => 'message-5'],
                        ['role' => 'assistant', 'content' => 'message-6'],
                    ], $context['conversation_history']);

                    return true;
                })
            )
            ->willReturn([
                'success' => true,
                'message' => 'ok',
                'agent' => 'steward',
            ]);

        app(NajmHodaExecutionService::class)->executeChat(
            $orchestrator,
            'new question',
            [
                'user_id' => $user->id,
                'conversation' => $conversation,
            ]
        );
    }

    private function makeUser(string $prefix): User
    {
        return User::create([
            'first_name' => ucfirst($prefix),
            'last_name' => 'Continuity',
            'email' => fake()->unique()->safeEmail(),
            'password' => bcrypt('password123'),
            'is_admin' => false,
        ]);
    }
}
