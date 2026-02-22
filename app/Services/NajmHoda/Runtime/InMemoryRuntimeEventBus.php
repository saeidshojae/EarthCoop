<?php

namespace App\Services\NajmHoda\Runtime;

use Carbon\CarbonImmutable;

class InMemoryRuntimeEventBus implements RuntimeEventBus
{
    /**
     * @var array<int, array<string, mixed>>
     */
    protected array $events = [];

    public function __construct(
        protected int $maxEvents = 500
    ) {
        $this->maxEvents = max(10, $this->maxEvents);
    }

    public function emit(string $event, array $payload = []): void
    {
        $payload = RuntimeEventEnvelope::normalize($event, $payload);

        $this->events[] = [
            'event' => $event,
            'payload' => $payload,
            'timestamp' => CarbonImmutable::now()->toIso8601String(),
        ];

        if (count($this->events) > $this->maxEvents) {
            $this->events = array_slice($this->events, -1 * $this->maxEvents);
        }
    }

    public function recent(?string $event = null, int $limit = 50): array
    {
        $limit = max(1, $limit);

        $events = $event === null
            ? $this->events
            : array_values(array_filter(
                $this->events,
                static fn (array $entry): bool => ($entry['event'] ?? null) === $event
            ));

        if (empty($events)) {
            return [];
        }

        return array_reverse(array_slice($events, -1 * $limit));
    }

    public function clear(): void
    {
        $this->events = [];
    }
}
