<?php

namespace App\Services\GroupChat;

use App\Events\GroupFeedUpdated;
use App\Events\GroupMessageUpdated;
use App\Events\GroupPollUpdated;
use App\Events\MessageCreated;
use Illuminate\Support\Facades\Log;

class GroupEventPublisher
{
    public function __construct(private readonly GroupSyncService $sync)
    {
    }

    public function publish(object $event): void
    {
        if (! (bool) config('group-chat.enabled', true)) {
            return;
        }

        try {
            $this->capture($event);
        } catch (\Throwable $exception) {
            Log::warning('group_chat_sync_capture_failed', [
                'event' => get_class($event),
                'message' => $exception->getMessage(),
            ]);
        }

        if (strtolower((string) config('group-chat.transport', 'auto')) === 'polling') {
            return;
        }

        $broadcast = static function () use ($event): void {
            try {
                event($event);
            } catch (\Throwable $exception) {
                Log::warning('group_chat_broadcast_failed', [
                    'event' => get_class($event),
                    'message' => $exception->getMessage(),
                ]);
            }
        };

        if ((bool) config('group-chat.defer_broadcasts', true)) {
            dispatch($broadcast)->afterResponse();
            return;
        }

        $broadcast();
    }

    private function capture(object $event): void
    {
        $groupId = null;
        $actorId = null;
        $eventType = get_class($event);
        $action = null;
        $payload = [];

        if ($event instanceof GroupFeedUpdated || $event instanceof GroupMessageUpdated) {
            $groupId = (int) $event->groupId;
            $actorId = $event->actorId ? (int) $event->actorId : null;
            $action = (string) $event->action;
            $payload = (array) $event->payload;
        } elseif ($event instanceof GroupPollUpdated) {
            $groupId = (int) $event->groupId;
            $actorId = $event->actorId ? (int) $event->actorId : null;
            $action = 'poll_voted';
            $payload = (array) $event->poll;
        } elseif ($event instanceof MessageCreated) {
            $groupId = (int) $event->group->id;
            $actorId = (int) $event->sender->id;
            $action = 'message_created';
            $payload = (array) ($event->broadcastWith()['message'] ?? []);
        }

        if (! $groupId || ! $action) {
            return;
        }

        [$contentType, $contentId] = $this->contentIdentity($action, $payload);
        // HTML fragments are user-sensitive (ownership, permissions and read
        // state). Store canonical identifiers/data and render fragments for
        // the authenticated consumer in the sync endpoint instead.
        if (in_array($contentType, ['post', 'poll', 'comment'], true)) {
            unset($payload['html']);
        }
        $this->sync->record($groupId, $eventType, $action, $payload, $actorId, $contentType, $contentId);
    }

    private function contentIdentity(string $action, array $payload): array
    {
        foreach (['message', 'post', 'poll', 'comment'] as $type) {
            $key = $type . '_id';
            if (! empty($payload[$key])) {
                return [$type, (int) $payload[$key]];
            }
        }

        if (str_starts_with($action, 'message_') && ! empty($payload['id'])) {
            return ['message', (int) $payload['id']];
        }
        if (str_starts_with($action, 'post_') && ! empty($payload['id'])) {
            return ['post', (int) $payload['id']];
        }
        if (str_starts_with($action, 'poll_') && ! empty($payload['id'])) {
            return ['poll', (int) $payload['id']];
        }

        return [null, null];
    }
}
