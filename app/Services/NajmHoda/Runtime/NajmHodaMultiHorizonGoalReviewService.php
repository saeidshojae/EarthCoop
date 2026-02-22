<?php

namespace App\Services\NajmHoda\Runtime;

use Illuminate\Support\Facades\Cache;

class NajmHodaMultiHorizonGoalReviewService
{
    protected string $snapshotKey = 'najm_hoda:multi_horizon_goals:last_snapshot';
    protected string $reviewKey = 'najm_hoda:multi_horizon_goals:last_review';

    public function __construct(
        protected RuntimeEventBus $eventBus,
        protected NajmHodaMultiHorizonGoalEngineService $engineService
    ) {
    }

    /**
     * @param array<string, mixed> $query
     * @return array<string, mixed>
     */
    public function review(array $query = []): array
    {
        $previous = Cache::get($this->snapshotKey, []);
        if (!is_array($previous)) {
            $previous = [];
        }

        $current = $this->engineService->buildBacklog($query);
        $comparison = $this->compare($previous, $current);
        $status = $this->determineStatus($comparison);

        $review = [
            'generated_at' => now()->toIso8601String(),
            'status' => $status,
            'scope' => (string) ($current['scope'] ?? 'global'),
            'comparison' => $comparison,
            'current' => [
                'backlog_count' => (int) count((array) ($current['backlog'] ?? [])),
                'high_priority_count' => $this->countByPriority((array) ($current['backlog'] ?? []), 'high'),
                'daily_goal_count' => count((array) data_get($current, 'horizons.daily', [])),
                'weekly_goal_count' => count((array) data_get($current, 'horizons.weekly', [])),
                'monthly_goal_count' => count((array) data_get($current, 'horizons.monthly', [])),
            ],
        ];

        $ttlMinutes = max(30, (int) config('najm-hoda.runtime.multi_horizon_goals.snapshot_ttl_minutes', 180));
        Cache::put($this->reviewKey, $review, now()->addMinutes($ttlMinutes));

        $this->eventBus->emit('najm_hoda.autonomy.multi_horizon_goals.reviewed', [
            'scope' => (string) ($review['scope'] ?? 'global'),
            'status' => $status,
            'backlog_delta' => (int) data_get($comparison, 'backlog_delta', 0),
            'high_priority_delta' => (int) data_get($comparison, 'high_priority_delta', 0),
            'daily_goal_delta' => (int) data_get($comparison, 'daily_goal_delta', 0),
            'risk' => $status === 'regressing' ? 'medium' : 'low',
        ]);

        return $review;
    }

    /**
     * @param array<string, mixed> $previous
     * @param array<string, mixed> $current
     * @return array<string, int>
     */
    protected function compare(array $previous, array $current): array
    {
        $prevBacklog = (array) ($previous['backlog'] ?? []);
        $currBacklog = (array) ($current['backlog'] ?? []);

        $prevBacklogCount = count($prevBacklog);
        $currBacklogCount = count($currBacklog);
        $prevHigh = $this->countByPriority($prevBacklog, 'high');
        $currHigh = $this->countByPriority($currBacklog, 'high');

        $prevDaily = count((array) data_get($previous, 'horizons.daily', []));
        $currDaily = count((array) data_get($current, 'horizons.daily', []));

        return [
            'backlog_delta' => $currBacklogCount - $prevBacklogCount,
            'high_priority_delta' => $currHigh - $prevHigh,
            'daily_goal_delta' => $currDaily - $prevDaily,
        ];
    }

    /**
     * @param array<string, int> $comparison
     */
    protected function determineStatus(array $comparison): string
    {
        $maxBacklogGrowth = (int) config('najm-hoda.runtime.multi_horizon_goals.review.max_backlog_growth', 1);
        $maxHighGrowth = (int) config('najm-hoda.runtime.multi_horizon_goals.review.max_high_priority_growth', 0);

        $backlogDelta = (int) ($comparison['backlog_delta'] ?? 0);
        $highDelta = (int) ($comparison['high_priority_delta'] ?? 0);

        if ($backlogDelta > $maxBacklogGrowth || $highDelta > $maxHighGrowth) {
            return 'regressing';
        }

        if ($backlogDelta < 0 || $highDelta < 0) {
            return 'improving';
        }

        return 'stable';
    }

    /**
     * @param array<int, array<string, mixed>> $backlog
     */
    protected function countByPriority(array $backlog, string $priority): int
    {
        $count = 0;
        foreach ($backlog as $item) {
            if ((string) ($item['priority'] ?? '') === $priority) {
                $count++;
            }
        }

        return $count;
    }
}

