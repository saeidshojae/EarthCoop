<?php

namespace App\Services\NajmHoda\Runtime;

use Illuminate\Support\Facades\Cache;

class NajmHodaAdaptivePolicyLearningService
{
    protected string $overrideKey = 'najm_hoda:autonomy:adaptive_safety_override';
    protected string $analysisHistoryKey = 'najm_hoda:autonomy:policy_learning:analysis_history';
    protected string $recommendationsKey = 'najm_hoda:autonomy:policy_learning:recommendations';

    public function __construct(
        protected RuntimeEventBus $eventBus,
        protected NajmHodaGovernanceMetricsAggregatorService $governanceMetrics,
        protected NajmHodaDecisionPolicyDriftService $driftService
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function analyze(?int $windowHours = null, bool $apply = false): array
    {
        $windowHours = max(1, $windowHours ?? (int) config('najm-hoda.runtime.autonomy.governance.window_hours', 24));
        $beforeOverride = $this->currentOverride();
        $snapshot = $this->governanceMetrics->snapshot($windowHours);
        $drift = $this->driftService->report($windowHours);

        $successRate = (float) data_get($snapshot, 'metrics.auto_action_success_rate', 1.0);
        $driftRate = (float) data_get($drift, 'drift_rate', 0.0);
        $driftStatus = (string) data_get($drift, 'status', 'ok');
        $decisionCount = (int) data_get($drift, 'total_decisions', 0);

        $targetSuccess = (float) config('najm-hoda.runtime.autonomy.governance.kpis.auto_action_success_rate.target_min', 0.95);
        $warningSuccess = (float) config('najm-hoda.runtime.autonomy.governance.kpis.auto_action_success_rate.warning_below', 0.9);

        $recommended = [
            'max_actions_per_run' => null,
            'allow_apply_low_risk' => null,
            'reason_codes' => [],
        ];
        $shouldApply = false;

        if ($driftStatus === 'breach' || $successRate < $warningSuccess) {
            $recommended['max_actions_per_run'] = 1;
            $recommended['allow_apply_low_risk'] = false;
            $recommended['reason_codes'][] = 'containment_mode';
            $shouldApply = true;
        } elseif ($driftStatus === 'warning' || $successRate < $targetSuccess) {
            $recommended['max_actions_per_run'] = 2;
            $recommended['allow_apply_low_risk'] = true;
            $recommended['reason_codes'][] = 'cautious_mode';
            $shouldApply = true;
        } else {
            $recommended['reason_codes'][] = 'stable_mode';
        }

        if ($decisionCount < 10) {
            $recommended['reason_codes'][] = 'low_sample_size';
            $shouldApply = false;
        }

        $report = [
            'recommendation_id' => null,
            'generated_at' => now()->toIso8601String(),
            'window_hours' => $windowHours,
            'input' => [
                'auto_action_success_rate' => $successRate,
                'policy_drift_rate' => $driftRate,
                'policy_drift_status' => $driftStatus,
                'total_decisions' => $decisionCount,
            ],
            'recommended_override' => $recommended,
            'should_apply' => $shouldApply,
            'applied' => false,
            'review' => [
                'status' => 'not_required',
                'requires_operator_review' => false,
            ],
            'evidence' => [
                'before_override' => $beforeOverride,
                'after_override' => $beforeOverride,
            ],
        ];

        if ($shouldApply) {
            $recommendation = $this->queueRecommendation($report);
            $report['recommendation_id'] = $recommendation['id'] ?? null;
            $report['review'] = [
                'status' => (string) ($recommendation['status'] ?? 'pending'),
                'requires_operator_review' => true,
            ];
        }

        if ($apply && $shouldApply) {
            $report['applied'] = $this->applyRecommendation(
                (string) ($report['recommendation_id'] ?? ''),
                auth()->id(),
                'auto_apply_from_policy_learning_loop'
            );
            $report['review'] = [
                'status' => $report['applied'] ? 'auto_applied' : 'apply_failed',
                'requires_operator_review' => true,
            ];
        } elseif ($apply) {
            $report['applied'] = $this->applyOverride($report, false);
        }
        $report['evidence']['after_override'] = $this->currentOverride();

        $this->appendAnalysisEvidence($report);

        $this->eventBus->emit('najm_hoda.autonomy.policy_learning.analyzed', [
            'window_hours' => $windowHours,
            'should_apply' => $shouldApply,
            'applied' => (bool) ($report['applied'] ?? false),
            'drift_status' => $driftStatus,
            'drift_rate' => $driftRate,
            'success_rate' => $successRate,
            'recommendation_id' => $report['recommendation_id'] ?? null,
            'review_status' => data_get($report, 'review.status', 'unknown'),
        ]);

        return $report;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function currentOverride(): ?array
    {
        $data = Cache::get($this->overrideKey);
        return is_array($data) ? $data : null;
    }

    public function clearOverride(?int $byUserId = null, ?string $reason = null): bool
    {
        $before = $this->currentOverride();
        Cache::forget($this->overrideKey);
        $after = $this->currentOverride();
        $this->eventBus->emit('najm_hoda.autonomy.policy_learning.override.cleared', [
            'by_user_id' => $byUserId,
            'reason' => $reason,
            'had_override_before_clear' => $before !== null,
        ]);
        $this->pushHistory(
            $this->analysisHistoryKey,
            [
                'id' => (string) \Illuminate\Support\Str::uuid(),
                'type' => 'override_cleared',
                'recorded_at' => now()->toIso8601String(),
                'by_user_id' => $byUserId,
                'reason' => $reason,
                'before_override' => $before,
                'after_override' => $after,
            ],
            max(50, (int) config('najm-hoda.runtime.autonomy.policy_learning.max_history', 300))
        );

        return true;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function listRecommendations(?string $status = null, int $limit = 50): array
    {
        $limit = max(1, min(300, $limit));
        $rows = Cache::get($this->recommendationsKey, []);
        if (!is_array($rows)) {
            $rows = [];
        }

        if ($status !== null && trim($status) !== '') {
            $rows = array_values(array_filter($rows, static function (array $row) use ($status): bool {
                return (string) ($row['status'] ?? '') === $status;
            }));
        }

        usort($rows, static function (array $a, array $b): int {
            return strcmp((string) ($b['created_at'] ?? ''), (string) ($a['created_at'] ?? ''));
        });

        return array_slice($rows, 0, $limit);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function recentEvidence(int $limit = 50): array
    {
        $limit = max(1, min(300, $limit));
        $rows = Cache::get($this->analysisHistoryKey, []);
        if (!is_array($rows)) {
            return [];
        }

        return array_slice($rows, 0, $limit);
    }

    /**
     * @return array<string, mixed>
     */
    public function reviewRecommendation(string $recommendationId, string $decision, ?int $reviewerUserId = null, ?string $reason = null): array
    {
        $decision = trim(strtolower($decision));
        if (!in_array($decision, ['approve', 'reject'], true)) {
            return [
                'success' => false,
                'reason' => 'invalid_decision',
            ];
        }

        $rows = Cache::get($this->recommendationsKey, []);
        if (!is_array($rows) || empty($rows)) {
            return [
                'success' => false,
                'reason' => 'recommendation_not_found',
            ];
        }

        $found = false;
        foreach ($rows as $index => $row) {
            if ((string) ($row['id'] ?? '') !== $recommendationId) {
                continue;
            }

            $found = true;
            if ((string) ($row['status'] ?? '') !== 'pending') {
                return [
                    'success' => false,
                    'reason' => 'recommendation_not_pending',
                    'recommendation' => $row,
                ];
            }

            $applied = false;
            if ($decision === 'approve') {
                $applied = $this->applyRecommendation($recommendationId, $reviewerUserId, $reason);
            } else {
                $this->eventBus->emit('najm_hoda.autonomy.policy_learning.recommendation.rejected', [
                    'recommendation_id' => $recommendationId,
                    'reviewer_user_id' => $reviewerUserId,
                    'reason' => $reason,
                ]);
            }

            $rows[$index]['status'] = $decision === 'approve' && $applied ? 'approved' : 'rejected';
            $rows[$index]['decision'] = $decision;
            $rows[$index]['reviewed_at'] = now()->toIso8601String();
            $rows[$index]['reviewer_user_id'] = $reviewerUserId;
            $rows[$index]['review_reason'] = $reason;
            $rows[$index]['applied'] = $applied;
            $rows[$index]['evidence_after_review'] = [
                'before_override' => $rows[$index]['evidence_before_review']['before_override'] ?? null,
                'after_override' => $this->currentOverride(),
            ];

            Cache::put(
                $this->recommendationsKey,
                $rows,
                now()->addMinutes(max(60, (int) config('najm-hoda.runtime.autonomy.policy_learning.retention_minutes', 10080)))
            );

            $this->pushHistory(
                $this->analysisHistoryKey,
                [
                    'id' => (string) \Illuminate\Support\Str::uuid(),
                    'type' => 'recommendation_reviewed',
                    'recorded_at' => now()->toIso8601String(),
                    'recommendation_id' => $recommendationId,
                    'decision' => $decision,
                    'reviewer_user_id' => $reviewerUserId,
                    'reason' => $reason,
                    'applied' => $applied,
                    'before_override' => $rows[$index]['evidence_before_review']['before_override'] ?? null,
                    'after_override' => $this->currentOverride(),
                ],
                max(50, (int) config('najm-hoda.runtime.autonomy.policy_learning.max_history', 300))
            );

            return [
                'success' => true,
                'recommendation' => $rows[$index],
                'applied' => $applied,
            ];
        }

        if (!$found) {
            return [
                'success' => false,
                'reason' => 'recommendation_not_found',
            ];
        }

        return [
            'success' => false,
            'reason' => 'review_failed',
        ];
    }

    /**
     * @param array<string, mixed> $report
     */
    protected function applyOverride(array $report, bool $shouldApply): bool
    {
        if (!$shouldApply) {
            $this->clearOverride(null, 'no_apply_required');
            return false;
        }

        $ttlMinutes = max(15, (int) config('najm-hoda.runtime.autonomy.policy_learning.override_ttl_minutes', 180));
        $override = [
            'version' => 1,
            'source' => 'adaptive_policy_learning',
            'applied_at' => now()->toIso8601String(),
            'expires_at' => now()->addMinutes($ttlMinutes)->toIso8601String(),
            'max_actions_per_run' => (int) data_get($report, 'recommended_override.max_actions_per_run', 0),
            'allow_apply_low_risk' => (bool) data_get($report, 'recommended_override.allow_apply_low_risk', false),
            'reason_codes' => (array) data_get($report, 'recommended_override.reason_codes', []),
        ];

        Cache::put($this->overrideKey, $override, now()->addMinutes($ttlMinutes));
        $this->eventBus->emit('najm_hoda.autonomy.policy_learning.override.applied', [
            'expires_at' => $override['expires_at'],
            'max_actions_per_run' => $override['max_actions_per_run'],
            'allow_apply_low_risk' => $override['allow_apply_low_risk'],
            'reason_codes' => $override['reason_codes'],
        ]);

        return true;
    }

    /**
     * @param array<string, mixed> $report
     * @return array<string, mixed>
     */
    protected function queueRecommendation(array $report): array
    {
        $recommendation = [
            'id' => (string) \Illuminate\Support\Str::uuid(),
            'status' => 'pending',
            'created_at' => now()->toIso8601String(),
            'expires_at' => now()->addMinutes(max(60, (int) config('najm-hoda.runtime.autonomy.policy_learning.review_ttl_minutes', 720)))->toIso8601String(),
            'window_hours' => (int) ($report['window_hours'] ?? 24),
            'input' => (array) ($report['input'] ?? []),
            'recommended_override' => (array) ($report['recommended_override'] ?? []),
            'reason_codes' => (array) data_get($report, 'recommended_override.reason_codes', []),
            'reviewed_at' => null,
            'reviewer_user_id' => null,
            'review_reason' => null,
            'decision' => null,
            'applied' => false,
            'evidence_before_review' => [
                'before_override' => $this->currentOverride(),
            ],
        ];

        $this->pushHistory(
            $this->recommendationsKey,
            $recommendation,
            max(50, (int) config('najm-hoda.runtime.autonomy.policy_learning.max_recommendations_history', 500)),
            max(60, (int) config('najm-hoda.runtime.autonomy.policy_learning.retention_minutes', 10080))
        );

        $this->eventBus->emit('najm_hoda.autonomy.policy_learning.recommendation.queued', [
            'recommendation_id' => $recommendation['id'],
            'window_hours' => $recommendation['window_hours'],
            'reason_codes' => $recommendation['reason_codes'],
            'expires_at' => $recommendation['expires_at'],
        ]);

        return $recommendation;
    }

    protected function applyRecommendation(string $recommendationId, ?int $reviewerUserId = null, ?string $reason = null): bool
    {
        if (trim($recommendationId) === '') {
            return false;
        }

        $rows = Cache::get($this->recommendationsKey, []);
        if (!is_array($rows) || empty($rows)) {
            return false;
        }

        foreach ($rows as $row) {
            if ((string) ($row['id'] ?? '') !== $recommendationId) {
                continue;
            }

            $report = [
                'recommended_override' => (array) ($row['recommended_override'] ?? []),
            ];
            $applied = $this->applyOverride($report, true);

            $this->eventBus->emit('najm_hoda.autonomy.policy_learning.recommendation.applied', [
                'recommendation_id' => $recommendationId,
                'reviewer_user_id' => $reviewerUserId,
                'reason' => $reason,
                'applied' => $applied,
            ]);

            return $applied;
        }

        return false;
    }

    /**
     * @param array<string, mixed> $report
     */
    protected function appendAnalysisEvidence(array $report): void
    {
        $this->pushHistory(
            $this->analysisHistoryKey,
            [
                'id' => (string) \Illuminate\Support\Str::uuid(),
                'type' => 'analysis',
                'recorded_at' => now()->toIso8601String(),
                'recommendation_id' => $report['recommendation_id'] ?? null,
                'window_hours' => (int) ($report['window_hours'] ?? 24),
                'input' => (array) ($report['input'] ?? []),
                'recommended_override' => (array) ($report['recommended_override'] ?? []),
                'should_apply' => (bool) ($report['should_apply'] ?? false),
                'applied' => (bool) ($report['applied'] ?? false),
                'review' => (array) ($report['review'] ?? []),
                'before_override' => data_get($report, 'evidence.before_override'),
                'after_override' => data_get($report, 'evidence.after_override'),
            ],
            max(50, (int) config('najm-hoda.runtime.autonomy.policy_learning.max_history', 300)),
            max(60, (int) config('najm-hoda.runtime.autonomy.policy_learning.retention_minutes', 10080))
        );
    }

    /**
     * @param array<string, mixed> $row
     */
    protected function pushHistory(string $cacheKey, array $row, int $maxItems, int $ttlMinutes = 10080): void
    {
        $maxItems = max(10, $maxItems);
        $ttlMinutes = max(30, $ttlMinutes);
        $rows = Cache::get($cacheKey, []);
        if (!is_array($rows)) {
            $rows = [];
        }

        array_unshift($rows, $row);
        $rows = array_slice($rows, 0, $maxItems);
        Cache::put($cacheKey, $rows, now()->addMinutes($ttlMinutes));
    }
}
