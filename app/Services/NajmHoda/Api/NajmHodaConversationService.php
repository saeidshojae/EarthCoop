<?php

namespace App\Services\NajmHoda\Api;

use App\Models\Conversation;
use App\Models\ConversationMessage;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class NajmHodaConversationService
{
    public function list(User $actor, array $filters = [], int $perPage = 20): LengthAwarePaginator
    {
        $query = Conversation::query()
            ->where('user_id', $actor->id)
            ->with('lastMessage')
            ->latest();

        if (!empty($filters['status'])) {
            $query->where('status', (string) $filters['status']);
        }

        if (!empty($filters['agent'])) {
            $query->where('agent_type', (string) $filters['agent']);
        }

        return $query->paginate(max(1, min(50, $perPage)));
    }

    public function get(User $actor, int $conversationId): Conversation
    {
        return Conversation::query()
            ->where('user_id', $actor->id)
            ->findOrFail($conversationId);
    }

    public function startOrGet(
        User $actor,
        ?int $conversationId,
        ?string $agentType = null,
        ?string $title = null
    ): Conversation {
        if ($conversationId !== null) {
            return $this->get($actor, $conversationId);
        }

        return Conversation::create([
            'user_id' => $actor->id,
            'title' => $title ?: 'بدون عنوان',
            'agent_type' => $agentType ?: 'auto',
            'status' => 'active',
        ]);
    }

    public function appendUserMessage(Conversation $conversation, string $message): ConversationMessage
    {
        return $conversation->messages()->create([
            'role' => 'user',
            'content' => $message,
        ]);
    }

    public function appendAssistantMessage(Conversation $conversation, string $message, string $agent): ConversationMessage
    {
        return $conversation->messages()->create([
            'role' => 'assistant',
            'content' => $message,
            'metadata' => [
                'agent' => $agent,
                'timestamp' => now()->toIso8601String(),
            ],
        ]);
    }
}
