<?php

namespace App\Jobs;

use App\Events\GroupRealtimeEnvelope;
use App\Models\GroupChatOutbox;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;

class PublishGroupChatOutbox implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 5;
    public array $backoff = [1, 5, 15, 30];

    public function __construct(public int $outboxId)
    {
        $this->onQueue('group-chat-realtime');
    }

    public function handle(): void
    {
        $row = DB::transaction(function () {
            $row = GroupChatOutbox::whereKey($this->outboxId)->lockForUpdate()->first();
            if (! $row || $row->status === 'published') return null;
            $row->update(['status' => 'processing', 'attempts' => $row->attempts + 1, 'last_error' => null]);
            return $row->fresh();
        });
        if (! $row) return;

        try {
            event(new GroupRealtimeEnvelope([
                'version' => 1,
                'event_id' => $row->event_id,
                'group_id' => (int) $row->group_id,
                'sequence' => (int) $row->sequence,
                'type' => $row->type,
                'actor_id' => $row->actor_id ? (int) $row->actor_id : null,
                'occurred_at' => $row->created_at->toIso8601String(),
                'payload' => $row->payload,
            ]));
            $row->update(['status' => 'published', 'published_at' => now(), 'last_error' => null]);
        } catch (\Throwable $exception) {
            $row->update(['status' => 'pending', 'available_at' => now()->addSeconds(min(60, 2 ** $row->attempts)), 'last_error' => mb_substr($exception->getMessage(), 0, 2000)]);
            throw $exception;
        }
    }
}
