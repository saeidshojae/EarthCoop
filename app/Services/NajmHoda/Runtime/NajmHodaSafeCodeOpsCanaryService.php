<?php

namespace App\Services\NajmHoda\Runtime;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class NajmHodaSafeCodeOpsCanaryService
{
    protected string $stateKey = 'najm_hoda:autonomy:codeops:canary:state';
    protected string $historyKey = 'najm_hoda:autonomy:codeops:canary:history';

    public function __construct(
        protected RuntimeEventBus $eventBus,
        protected NajmHodaGovernanceMetricsAggregatorService $governanceMetrics,
        protected NajmHodaDecisionPolicyDriftService $driftService,
        protected NajmHodaAutonomyControlService $controlService
    ) {
    }

    /**
     * @param array<int, int>|null $phases
     * @return array<string, mixed>
     */
    public function startCanary(?int $byUserId = null, ?string $reason = null, ?array $phases = null, ?int $windowHours = null): array
    {
        $status = $this->status();
        if (in_array((string) ($status['status'] ?? ''), ['canary', 'promoted'], true)) {
            return [
                'success' => false,
                'reason' => 'canary_already_active',
                'state' => $status,
            ];
        }

        $phases = $this->normalizePhases($phases);
        $windowHours = max(1, $windowHours ?? (int) config('najm-hoda.runtime.autonomy.codeops.window_hours', 24));
        $baseline = $this->healthReport($windowHours);

        $state = [
            'rollout_id' => (string) Str::uuid(),
            'status' => 'canary',
            'phase_index' => 0,
            'phase_percent' => $phases[0],
            'phases' => $phases,
            'window_hours' => $windowHours,
            'started_by' => $byUserId,
            'started_at' => now()->toIso8601String(),
            'updated_at' => now()->toIso8601String(),
            'reason' => $reason,
            'baseline' => $baseline,
            'last_health' => $baseline,
            'rollback' => null,
        ];
        $this->storeState($state);
        $this->appendHistory([
            'type' => 'canary_started',
            'timestamp' => now()->toIso8601String(),
            'rollout_id' => $state['rollout_id'],
            'phase_percent' => $state['phase_percent'],
            'reason' => $reason,
            'by_user_id' => $byUserId,
            'health_status' => (string) ($baseline['status'] ?? 'unknown'),
        ]);

        $this->eventBus->emit('najm_hoda.autonomy.codeops.canary.started', [
            'rollout_id' => $state['rollout_id'],
            'phase_percent' => $state['phase_percent'],
            'reason' => $reason,
            'by_user_id' => $byUserId,
            'health_status' => (string) ($baseline['status'] ?? 'unknown'),
        ]);

        return [
            'success' => true,
            'state' => $state,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function promote(?int $byUserId = null, ?string $reason = null): array
    {
        $state = $this->status();
        if ((string) ($state['status'] ?? '') !== 'canary') {
            return [
                'success' => false,
                'reason' => 'canary_not_active',
                'state' => $state,
            ];
        }

        $health = $this->healthReport((int) ($state['window_hours'] ?? 24));
        if (in_array((string) ($health['status'] ?? 'breach'), ['breach'], true)) {
            return [
                'success' => false,
                'reason' => 'health_breach_blocks_promotion',
                'health' => $health,
                'state' => $state,
            ];
        }

        $phases = is_array($state['phases'] ?? null) ? $state['phases'] : $this->normalizePhases(null);
        $currentIndex = max(0, (int) ($state['phase_index'] ?? 0));
        $nextIndex = $currentIndex + 1;

        if (!isset($phases[$nextIndex])) {
            $state['status'] = 'promoted';
            $state['phase_index'] = $currentIndex;
            $state['phase_percent'] = (int) ($phases[$currentIndex] ?? 100);
        } else {
            $state['phase_index'] = $nextIndex;
            $state['phase_percent'] = (int) $phases[$nextIndex];
        }
        $state['updated_at'] = now()->toIso8601String();
        $state['last_health'] = $health;
        $this->storeState($state);

        $this->appendHistory([
            'type' => 'canary_promoted',
            'timestamp' => now()->toIso8601String(),
            'rollout_id' => (string) ($state['rollout_id'] ?? ''),
            'phase_percent' => (int) ($state['phase_percent'] ?? 0),
            'status' => (string) ($state['status'] ?? 'canary'),
            'reason' => $reason,
            'by_user_id' => $byUserId,
            'health_status' => (string) ($health['status'] ?? 'unknown'),
        ]);

        $this->eventBus->emit('najm_hoda.autonomy.codeops.canary.promoted', [
            'rollout_id' => (string) ($state['rollout_id'] ?? ''),
            'phase_percent' => (int) ($state['phase_percent'] ?? 0),
            'status' => (string) ($state['status'] ?? 'canary'),
            'reason' => $reason,
            'by_user_id' => $byUserId,
            'health_status' => (string) ($health['status'] ?? 'unknown'),
        ]);

        return [
            'success' => true,
            'state' => $state,
            'health' => $health,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function evaluate(bool $autoRollback = false, ?int $byUserId = null, ?string $reason = null): array
    {
        $state = $this->status();
        $windowHours = max(1, (int) ($state['window_hours'] ?? (int) config('najm-hoda.runtime.autonomy.codeops.window_hours', 24)));
        $health = $this->healthReport($windowHours);

        if (!in_array((string) ($state['status'] ?? ''), ['canary', 'promoted'], true)) {
            return [
                'success' => true,
                'active' => false,
                'health' => $health,
                'state' => $state,
            ];
        }

        $state['last_health'] = $health;
        $state['updated_at'] = now()->toIso8601String();
        $this->storeState($state);

        $this->eventBus->emit('najm_hoda.autonomy.codeops.canary.evaluated', [
            'rollout_id' => (string) ($state['rollout_id'] ?? ''),
            'status' => (string) ($state['status'] ?? 'unknown'),
            'phase_percent' => (int) ($state['phase_percent'] ?? 0),
            'health_status' => (string) ($health['status'] ?? 'unknown'),
            'auto_rollback' => $autoRollback,
        ]);

        if ($autoRollback && (string) ($health['status'] ?? 'ok') === 'breach') {
            $rollbackReason = $reason !== null && trim($reason) !== '' ? trim($reason) : 'codeops_auto_rollback_slo_breach';
            return $this->rollback($byUserId, $rollbackReason, $health);
        }

        return [
            'success' => true,
            'active' => true,
            'health' => $health,
            'state' => $state,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function rollback(?int $byUserId = null, ?string $reason = null, ?array $health = null): array
    {
        $state = $this->status();
        if (!in_array((string) ($state['status'] ?? ''), ['canary', 'promoted'], true)) {
            return [
                'success' => false,
                'reason' => 'canary_not_active',
                'state' => $state,
            ];
        }

        $this->controlService->setOverride(
            'propose',
            [],
            false,
            $byUserId,
            $reason !== null ? trim($reason) : 'codeops_manual_rollback'
        );
        $this->controlService->pause(
            $byUserId,
            'codeops_canary_rollback_pause',
            max(5, (int) config('najm-hoda.runtime.autonomy.codeops.rollback_pause_minutes', 30))
        );

        $state['status'] = 'rolled_back';
        $state['updated_at'] = now()->toIso8601String();
        $state['rollback'] = [
            'at' => now()->toIso8601String(),
            'by_user_id' => $byUserId,
            'reason' => $reason,
            'health' => $health ?? $this->healthReport((int) ($state['window_hours'] ?? 24)),
        ];
        $this->storeState($state);

        $this->appendHistory([
            'type' => 'canary_rolled_back',
            'timestamp' => now()->toIso8601String(),
            'rollout_id' => (string) ($state['rollout_id'] ?? ''),
            'phase_percent' => (int) ($state['phase_percent'] ?? 0),
            'reason' => $reason,
            'by_user_id' => $byUserId,
            'health_status' => (string) data_get($state, 'rollback.health.status', 'unknown'),
        ]);

        $this->eventBus->emit('najm_hoda.autonomy.codeops.canary.rolled_back', [
            'rollout_id' => (string) ($state['rollout_id'] ?? ''),
            'phase_percent' => (int) ($state['phase_percent'] ?? 0),
            'reason' => $reason,
            'by_user_id' => $byUserId,
            'health_status' => (string) data_get($state, 'rollback.health.status', 'unknown'),
        ]);

        return [
            'success' => true,
            'state' => $state,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function status(): array
    {
        $data = Cache::get($this->stateKey, []);
        if (!is_array($data)) {
            $data = [];
        }

        return array_merge([
            'rollout_id' => null,
            'status' => 'idle',
            'phase_index' => null,
            'phase_percent' => null,
            'phases' => $this->normalizePhases(null),
            'window_hours' => (int) config('najm-hoda.runtime.autonomy.codeops.window_hours', 24),
            'started_by' => null,
            'started_at' => null,
            'updated_at' => null,
            'reason' => null,
            'baseline' => null,
            'last_health' => null,
            'rollback' => null,
        ], $data);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function history(int $limit = 50): array
    {
        $limit = max(1, min(300, $limit));
        $rows = Cache::get($this->historyKey, []);
        if (!is_array($rows)) {
            return [];
        }
        return array_slice($rows, 0, $limit);
    }

    /**
     * @return array<string, mixed>
     */
    protected function healthReport(int $windowHours): array
    {
        $snapshot = $this->governanceMetrics->snapshot($windowHours);
        $drift = $this->driftService->report($windowHours);
        $evaluation = is_array($snapshot['evaluation'] ?? null) ? $snapshot['evaluation'] : [];

        $breachCount = 0;
        $warningCount = 0;
        foreach ($evaluation as $item) {
            $status = (string) ($item['status'] ?? 'no_data');
            if ($status === 'breach') {
                $breachCount++;
            } elseif ($status === 'warning') {
                $warningCount++;
            }
        }

        $driftStatus = (string) ($drift['status'] ?? 'ok');
        if ($driftStatus === 'breach') {
            $breachCount++;
        } elseif ($driftStatus === 'warning') {
            $warningCount++;
        }

        $status = 'ok';
        if ($breachCount > 0) {
            $status = 'breach';
        } elseif ($warningCount > max(0, (int) config('najm-hoda.runtime.autonomy.codeops.max_warnings_for_progress', 1))) {
            $status = 'warning';
        }

        return [
            'generated_at' => now()->toIso8601String(),
            'window_hours' => $windowHours,
            'status' => $status,
            'breach_count' => $breachCount,
            'warning_count' => $warningCount,
            'drift_status' => $driftStatus,
            'drift_rate' => (float) ($drift['drift_rate'] ?? 0.0),
            'snapshot' => [
                'event_count' => (int) ($snapshot['event_count'] ?? 0),
                'metrics' => (array) ($snapshot['metrics'] ?? []),
                'evaluation' => $evaluation,
            ],
        ];
    }

    /**
     * @param array<int, int>|null $phases
     * @return array<int, int>
     */
    protected function normalizePhases(?array $phases): array
    {
        if (!is_array($phases) || empty($phases)) {
            $phases = (array) config('najm-hoda.runtime.autonomy.codeops.canary_phases', [5, 25, 50, 100]);
        }

        $normalized = [];
        foreach ($phases as $phase) {
            if (!is_numeric($phase)) {
                continue;
            }
            $normalized[] = max(1, min(100, (int) $phase));
        }

        if (empty($normalized)) {
            $normalized = [5, 25, 50, 100];
        }

        $normalized = array_values(array_unique($normalized));
        sort($normalized);
        if (!in_array(100, $normalized, true)) {
            $normalized[] = 100;
        }

        return $normalized;
    }

    /**
     * @param array<string, mixed> $state
     */
    protected function storeState(array $state): void
    {
        Cache::put(
            $this->stateKey,
            $state,
            now()->addMinutes(max(60, (int) config('najm-hoda.runtime.autonomy.codeops.retention_minutes', 20160)))
        );
    }

    /**
     * @param array<string, mixed> $row
     */
    protected function appendHistory(array $row): void
    {
        $history = Cache::get($this->historyKey, []);
        if (!is_array($history)) {
            $history = [];
        }
        array_unshift($history, $row);
        $history = array_slice(
            $history,
            0,
            max(20, (int) config('najm-hoda.runtime.autonomy.codeops.history_size', 500))
        );
        Cache::put(
            $this->historyKey,
            $history,
            now()->addMinutes(max(60, (int) config('najm-hoda.runtime.autonomy.codeops.retention_minutes', 20160)))
        );
    }
}

