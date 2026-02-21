<?php

namespace App\Services\NajmHoda\Runtime;

use Illuminate\Support\Facades\Cache;

class NajmHodaAutonomyAuditService
{
    protected string $historyKey = 'najm_hoda:autonomy:audit:history';

    public function __construct(
        protected RuntimeEventBus $eventBus
    ) {
    }

    /**
     * @param array<string, mixed> $result
     * @return array<string, mixed>
     */
    public function record(array $result): array
    {
        $trace = [
            'run_id' => (string) ($result['run_id'] ?? ''),
            'status' => (string) ($result['status'] ?? 'unknown'),
            'executed' => (bool) ($result['executed'] ?? false),
            'goals' => (array) ($result['goals'] ?? []),
            'context' => (array) ($result['context'] ?? []),
            'recommendations' => (array) ($result['recommendations'] ?? []),
            'plan' => (array) ($result['plan'] ?? []),
            'execution_results' => (array) ($result['execution_results'] ?? []),
            'control_state' => (array) ($result['control_state'] ?? []),
            'control_override' => (array) ($result['control_override'] ?? []),
            'apply_requested' => (bool) ($result['apply_requested'] ?? false),
            'generated_at' => (string) ($result['generated_at'] ?? now()->toIso8601String()),
            'recorded_at' => now()->toIso8601String(),
        ];

        $history = $this->history();
        array_unshift($history, $trace);

        $maxSize = max(20, (int) config('najm-hoda.runtime.autonomy.audit.history_size', 500));
        $history = array_slice($history, 0, $maxSize);
        $this->storeHistory($history);

        $this->eventBus->emit('najm_hoda.autonomy.audit.recorded', [
            'run_id' => $trace['run_id'],
            'status' => $trace['status'],
            'plan_count' => count($trace['plan']),
            'execution_count' => count($trace['execution_results']),
        ]);

        return $trace;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function history(int $limit = 200): array
    {
        $limit = max(1, min(1000, $limit));
        $history = Cache::get($this->historyKey, []);
        if (!is_array($history)) {
            return [];
        }
        return array_slice($history, 0, $limit);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function find(string $runId): ?array
    {
        $runId = trim($runId);
        if ($runId === '') {
            return null;
        }

        foreach ($this->history(1000) as $trace) {
            if ((string) ($trace['run_id'] ?? '') === $runId) {
                return $trace;
            }
        }

        return null;
    }

    /**
     * @return array<string, mixed>
     */
    public function replay(string $runId): array
    {
        $trace = $this->find($runId);
        if ($trace === null) {
            return ['success' => false, 'reason' => 'trace_not_found'];
        }

        $payload = [
            'success' => true,
            'run_id' => (string) ($trace['run_id'] ?? ''),
            'status' => (string) ($trace['status'] ?? ''),
            'goals' => (array) ($trace['goals'] ?? []),
            'plan' => (array) ($trace['plan'] ?? []),
            'execution_results' => (array) ($trace['execution_results'] ?? []),
            'recorded_at' => (string) ($trace['recorded_at'] ?? ''),
            'replayed_at' => now()->toIso8601String(),
        ];

        $this->eventBus->emit('najm_hoda.autonomy.audit.replayed', [
            'run_id' => $payload['run_id'],
            'status' => $payload['status'],
            'plan_count' => count($payload['plan']),
        ]);

        return $payload;
    }

    /**
     * @param array<int, array<string, mixed>> $history
     */
    protected function storeHistory(array $history): void
    {
        $ttlMinutes = max(60, (int) config('najm-hoda.runtime.autonomy.audit.retention_minutes', 10080));
        Cache::put($this->historyKey, $history, now()->addMinutes($ttlMinutes));
    }
}
