<?php

namespace App\Listeners;

use App\Events\BlogCreated;
use App\Events\GroupFeedUpdated;
use App\Events\PollCreated;
use App\Services\GroupChat\GroupEventPublisher;
use App\Services\GroupChat\GroupFeedService;

/**
 * System identities (Najm Hoda, future system assistants) can publish group
 * artifacts outside the HTTP controllers. Controllers already record the
 * canonical feed item and emit GroupFeedUpdated, but those system paths only
 * emitted the legacy domain event. Bridge system-authored artifacts back into
 * the same canonical feed/realtime pipeline without duplicating normal user
 * controller events.
 *
 * Important: delegated system publications are not optimistic writes by the
 * authenticated browser user. Realtime actor_id is therefore intentionally 0
 * so the client does not suppress the event as a normal self-echo. The actual
 * system author remains explicit in system_actor_id and in the persisted model.
 */
class BridgeSystemGroupArtifactToRealtimeFeed
{
    public function __construct(
        private readonly GroupFeedService $feed,
        private readonly GroupEventPublisher $publisher,
    ) {
    }

    public function handle(object $event): void
    {
        if ($event instanceof PollCreated) {
            $this->bridgePoll($event);
            return;
        }

        if ($event instanceof BlogCreated) {
            $this->bridgePost($event);
        }
    }

    private function bridgePoll(PollCreated $event): void
    {
        $poll = $event->poll;
        $group = $event->group;
        $creator = $event->creator;

        if (! (bool) ($creator->is_system ?? false)) {
            return;
        }

        $this->feed->record(
            (int) $group->id,
            'poll',
            (int) $poll->id,
            (int) $creator->id,
            $poll->created_at
        );

        $this->publisher->publish(new GroupFeedUpdated(
            (int) $group->id,
            'poll_created',
            [
                'poll_id' => (int) $poll->id,
                'system_authored' => true,
                'system_actor_id' => (int) $creator->id,
            ],
            0
        ));
    }

    private function bridgePost(BlogCreated $event): void
    {
        $post = $event->blog;
        $group = $event->group;
        $author = $event->author;

        if (! (bool) ($author->is_system ?? false)) {
            return;
        }

        $this->feed->record(
            (int) $group->id,
            'post',
            (int) $post->id,
            (int) $author->id,
            $post->created_at
        );

        $this->publisher->publish(new GroupFeedUpdated(
            (int) $group->id,
            'post_created',
            [
                'post_id' => (int) $post->id,
                'system_authored' => true,
                'system_actor_id' => (int) $author->id,
            ],
            0
        ));
    }
}
