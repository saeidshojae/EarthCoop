<?php

namespace App\Listeners;

use App\Events\MessageCreated;
use App\Services\NajmHoda\NajmHodaGroupAssistantService;
use Illuminate\Support\Facades\Log;

class HandleNajmHodaGroupMessage
{
    public function __construct(private NajmHodaGroupAssistantService $assistant)
    {
    }

    public function handle(MessageCreated $event): void
    {
        if (!config('najm-hoda.enabled', true)) {
            Log::info('NajmHoda group assistant skipped because system is disabled', [
                'group_id' => $event->group->id ?? null,
                'message_id' => $event->message->id ?? null,
            ]);
            return;
        }

        // Legacy public-chat conversations are disabled by default. Managers and
        // members talk to Najm Hoda privately in the widget; only the resulting
        // artifact/action is published to the group. A deliberate feature flag
        // can re-enable public mention mode later without changing this contract.
        if (!(bool) config('najm-hoda-group-runtime.public_chat_enabled', false)) {
            return;
        }

        try {
            $this->assistant->handleIncomingMessage($event->message);
        } catch (\Throwable $e) {
            Log::warning('NajmHoda group assistant failed', [
                'group_id' => $event->group->id ?? null,
                'message_id' => $event->message->id ?? null,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
