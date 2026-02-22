<?php

namespace App\Services\NajmHoda\Runtime;

use Illuminate\Support\Facades\Cache;

class NajmHodaAdaptivePolicyLearningService
{
    protected string $overrideKey = 'najm_hoda:autonomy:adaptive_safety_override';

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
        ];

        if ($apply) {
            $report['applied'] = $this->applyOverride($report, $shouldApply);
        }

        $this->eventBus->emit('najm_hoda.autonomy.policy_learning.analyzed', [
            'window_hours' => $windowHours,
            'should_apply' => $shouldApply,
            'applied' => (bool) ($report['applied'] ?? false),
            'drift_status' => $driftStatus,
            'drift_rate' => $driftRate,
            'success_rate' => $successRate,
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
        Cache::forget($this->overrideKey);
        $this->eventBus->emit('najm_hoda.autonomy.policy_learning.override.cleared', [
            'by_user_id' => $byUserId,
            'reason' => $reason,
        ]);

        return true;
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
}

