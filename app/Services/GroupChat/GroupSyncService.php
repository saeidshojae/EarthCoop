<?php

namespace App\Services\GroupChat;

use App\Models\GroupSyncEvent;
use Illuminate\Support\Facades\Schema;

class GroupSyncService
{
    public function available(): bool
    {
        return Schema::hasTable('group_sync_events');
    }

    public function record(
        int $groupId,
        string $eventType,
        string $action,
        array $payload = [],
        ?int $actorId = null,
        ?string $contentType = null,
        ?int $contentId = null
    ): ?GroupSyncEvent {
        if (! $this->available()) {
            return null;
        }

        return GroupSyncEvent::create([
            'group_id' => $groupId,
            'event_type' => $eventType,
            'action' => $action,
            'content_type' => $contentType,
            'content_id' => $contentId,
            'actor_id' => $actorId,
            'payload' => $payload,
            'occurred_at' => now(),
        ]);
    }
}
