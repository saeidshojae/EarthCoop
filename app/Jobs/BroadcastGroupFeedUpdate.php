<?php

namespace App\Jobs;

use App\Events\GroupFeedUpdated;

class BroadcastGroupFeedUpdate
{
    public function __construct(
        public int $groupId,
        public string $action,
        public array $payload,
        public int $actorId
    ) {
    }

    public function handle(): void
    {
        event(new GroupFeedUpdated($this->groupId, $this->action, $this->payload, $this->actorId));
    }
}
