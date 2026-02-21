<?php

namespace App\Services\NajmHoda\Runtime;

use App\Models\Group;
use App\Models\Message;
use App\Models\NajmHodaGroupActionItem;
use Illuminate\Support\Facades\Cache;

class NajmHodaObservabilityGraphService
{
    public function __construct(
        protected RuntimeEventBus $eventBus
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function snapshot(?int $eventLimit = null): array
    {
        $eventLimit = $eventLimit ?? (int) config('najm-hoda.runtime.autonomy.observability.event_limit', 300);
        $eventLimit = max(50, $eventLimit);

        $events = $this->eventBus->recent(null, $eventLimit);
        $runtime = $this->buildRuntimeSignals($events);

        $windowHours = max(1, (int) config('najm-hoda.runtime.autonomy.observability.window_hours', 24));
        $since = now()->subHours($windowHours);

        $chatSignals = [
            'messages_recent' => $this->safeCount(static fn (): int => Message::query()->where('created_at', '>=', $since)->count()),
        ];

        $groupSignals = [
            'groups_total' => $this->safeCount(static fn (): int => Group::query()->count()),
        ];

        $assignmentSignals = [
            'open' => $this->safeCount(static fn (): int => NajmHodaGroupActionItem::query()->where('status', 'open')->count()),
            'in_progress' => $this->safeCount(static fn (): int => NajmHodaGroupActionItem::query()->where('status', 'in_progress')->count()),
            'overdue' => $this->safeCount(static fn (): int => NajmHodaGroupActionItem::query()
                ->whereNotIn('status', ['done', 'cancelled'])
                ->whereNotNull('due_at')
                ->where('due_at', '<', now())
                ->count()),
        ];

        $snapshot = [
            'generated_at' => now()->toIso8601String(),
            'window_hours' => $windowHours,
            'event_limit' => $eventLimit,
            'runtime' => $runtime,
            'modules' => [
                'chat' => $chatSignals,
                'groups' => $groupSignals,
                'assignments' => $assignmentSignals,
            ],
            'error_rate_percent' => (float) ($runtime['error_rate_percent'] ?? 0.0),
            'unresolved_requests' => (int) ($runtime['unresolved_requests'] ?? 0),
        ];

        $ttlMinutes = max(30, (int) config('najm-hoda.runtime.autonomy.observability.snapshot_ttl_minutes', 180));
        Cache::put('najm_hoda:autonomy:last_observability_snapshot', $snapshot, now()->addMinutes($ttlMinutes));

        $this->eventBus->emit('najm_hoda.autonomy.observability.snapshot', [
            'error_rate_percent' => (float) ($snapshot['error_rate_percent'] ?? 0.0),
            'unresolved_requests' => (int) ($snapshot['unresolved_requests'] ?? 0),
            'chat_messages_recent' => (int) data_get($snapshot, 'modules.chat.messages_recent', 0),
            'groups_total' => (int) data_get($snapshot, 'modules.groups.groups_total', 0),
            'assignments_open' => (int) data_get($snapshot, 'modules.assignments.open', 0),
            'assignments_overdue' => (int) data_get($snapshot, 'modules.assignments.overdue', 0),
        ]);

        return $snapshot;
    }

    /**
     * @param array<int, array<string, mixed>> $events
     * @return array<string, mixed>
     */
    protected function buildRuntimeSignals(array $events): array
    {
        $received = 0;
        $ready = 0;
        $failed = 0;

        foreach ($events as $event) {
            $name = (string) ($event['event'] ?? '');
            if ($name === 'najm_hoda.request.received') {
                $received++;
                continue;
            }
            if ($name === 'najm_hoda.response.ready') {
                $ready++;
                continue;
            }
            if ($name === 'najm_hoda.response.failed') {
                $failed++;
            }
        }

        $resolved = $ready + $failed;
        $errorRatePercent = $resolved > 0 ? round(($failed / $resolved) * 100, 2) : 0.0;
        $unresolved = max(0, $received - $resolved);

        return [
            'events_total' => count($events),
            'request_received' => $received,
            'response_ready' => $ready,
            'response_failed' => $failed,
            'unresolved_requests' => $unresolved,
            'error_rate_percent' => $errorRatePercent,
        ];
    }

    protected function safeCount(callable $resolver): int
    {
        try {
            return (int) $resolver();
        } catch (\Throwable) {
            return 0;
        }
    }
}
