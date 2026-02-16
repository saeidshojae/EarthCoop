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
