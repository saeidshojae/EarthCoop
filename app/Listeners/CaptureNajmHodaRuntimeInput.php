<?php

namespace App\Listeners;

use App\Events\ElectionFinished;
use App\Events\ElectionStarted;
use App\Events\GroupFeedUpdated;
use App\Events\GroupPollUpdated;
use App\Events\MessageCreated;
use App\Services\NajmHoda\Runtime\RuntimeEventBus;

class CaptureNajmHodaRuntimeInput
{
    public function __construct(
        private RuntimeEventBus $runtimeEventBus
    ) {
    }

    public function handle(object $event): void
    {
        if (!config('najm-hoda.enabled', true)) {
            return;
        }

        if ($event instanceof MessageCreated) {
            $this->runtimeEventBus->emit('najm_hoda.input.group_message', [
                'source_event' => MessageCreated::class,
                'group_id' => (int) ($event->group->id ?? 0),
                'message_id' => (int) ($event->message->id ?? 0),
                'sender_user_id' => (int) ($event->sender->id ?? 0),
                'parent_id' => $event->message->parent_id ?? null,
            ]);
            return;
        }

        if ($event instanceof GroupPollUpdated) {
            $this->runtimeEventBus->emit('najm_hoda.input.group_poll', [
                'source_event' => GroupPollUpdated::class,
                'group_id' => (int) $event->groupId,
                'actor_id' => $event->actorId,
                'poll_id' => (int) ($event->poll['id'] ?? 0),
                'status' => (string) ($event->poll['status'] ?? 'unknown'),
            ]);
            return;
        }

        if ($event instanceof GroupFeedUpdated) {
            $this->runtimeEventBus->emit('najm_hoda.input.group_feed', [
                'source_event' => GroupFeedUpdated::class,
                'group_id' => (int) $event->groupId,
                'actor_id' => (int) $event->actorId,
                'action' => (string) $event->action,
                'payload_keys' => array_keys((array) $event->payload),
            ]);
            return;
        }

        if ($event instanceof ElectionStarted) {
            $this->runtimeEventBus->emit('najm_hoda.input.group_election_started', [
                'source_event' => ElectionStarted::class,
                'group_id' => (int) ($event->group->id ?? 0),
                'election_id' => (int) ($event->election->id ?? 0),
            ]);
            return;
        }

        if ($event instanceof ElectionFinished) {
            $this->runtimeEventBus->emit('najm_hoda.input.group_election_finished', [
                'source_event' => ElectionFinished::class,
                'group_id' => (int) ($event->group->id ?? 0),
                'election_id' => (int) ($event->election->id ?? 0),
                'elected_count' => (int) ($event->electedCandidates->count() ?? 0),
            ]);
        }
    }
}
