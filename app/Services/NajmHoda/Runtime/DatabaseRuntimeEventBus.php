<?php

namespace App\Services\NajmHoda\Runtime;

use App\Models\NajmHodaRuntimeEvent;
use Illuminate\Support\Facades\Log;
use Throwable;

class DatabaseRuntimeEventBus implements RuntimeEventBus
{
    protected InMemoryRuntimeEventBus $fallback;

    protected ?int $lastPruneTimestamp = null;

    public function __construct(
        protected int $maxEvents = 500,
        protected int $retentionDays = 14,
        protected int $pruneIntervalSeconds = 300
    ) {
        $this->maxEvents = max(10, $this->maxEvents);
        $this->retentionDays = max(1, $this->retentionDays);
        $this->pruneIntervalSeconds = max(30, $this->pruneIntervalSeconds);
        $this->fallback = new InMemoryRuntimeEventBus($this->maxEvents);
    }

    public function emit(string $event, array $payload = []): void
    {
        $requestId = isset($payload['request_id']) ? (string) $payload['request_id'] : null;

        try {
            NajmHodaRuntimeEvent::query()->create([
                'event' => $event,
                'request_id' => $requestId,
                'payload' => $payload,
            ]);

            $this->pruneIfNeeded();
            return;
        } catch (Throwable $exception) {
            Log::warning('NajmHoda runtime DB event bus failed, using in-memory fallback', [
                'event' => $event,
                'error' => $exception->getMessage(),
            ]);
        }

        $this->fallback->emit($event, $payload);
    }

    public function recent(?string $event = null, int $limit = 50): array
    {
        $limit = max(1, $limit);

        try {
            $query = NajmHodaRuntimeEvent::query()
                ->select(['event', 'payload', 'created_at'])
                ->latest('id')
                ->limit($limit);

            if ($event !== null) {
                $query->where('event', $event);
            }

            return $query->get()
                ->map(static function (NajmHodaRuntimeEvent $entry): array {
                    return [
                        'event' => $entry->event,
                        'payload' => is_array($entry->payload) ? $entry->payload : [],
                        'timestamp' => optional($entry->created_at)->toIso8601String(),
                    ];
                })
                ->all();
        } catch (Throwable $exception) {
            Log::warning('NajmHoda runtime DB event read failed, returning in-memory fallback', [
                'event' => $event,
                'error' => $exception->getMessage(),
            ]);

            return $this->fallback->recent($event, $limit);
        }
    }

    public function clear(): void
    {
        try {
            NajmHodaRuntimeEvent::query()->delete();
        } catch (Throwable $exception) {
            Log::warning('NajmHoda runtime DB event clear failed', [
                'error' => $exception->getMessage(),
            ]);
        }

        $this->fallback->clear();
    }

    protected function pruneIfNeeded(): void
    {
        $now = time();
        if ($this->lastPruneTimestamp !== null && ($now - $this->lastPruneTimestamp) < $this->pruneIntervalSeconds) {
            return;
        }

        $this->lastPruneTimestamp = $now;
        $cutoff = now()->subDays($this->retentionDays);

        try {
            NajmHodaRuntimeEvent::query()
                ->where('created_at', '<', $cutoff)
                ->delete();
        } catch (Throwable $exception) {
            Log::warning('NajmHoda runtime DB retention prune failed', [
                'cutoff' => $cutoff->toIso8601String(),
                'error' => $exception->getMessage(),
            ]);
        }
    }
}

