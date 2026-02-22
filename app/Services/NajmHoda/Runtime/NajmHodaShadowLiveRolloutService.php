<?php

namespace App\Services\NajmHoda\Runtime;

use Illuminate\Support\Facades\Cache;

class NajmHodaShadowLiveRolloutService
{
    protected string $stateKey = 'najm_hoda:autonomy:shadow_rollout:state';
    protected string $historyKey = 'najm_hoda:autonomy:shadow_rollout:history';

    public function __construct(
        protected RuntimeEventBus $eventBus,
        protected NajmHodaContinuousEvaluationHarnessService $evaluationService,
        protected NajmHodaSafeCodeOpsCanaryService $codeOpsCanaryService,
        protected NajmHodaOperationalAutonomyActivationService $operationsService,
        protected NajmHodaGovernanceAlertingService $alertingService
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function status(): array
    {
        $state = Cache::get($this->stateKey, []);
        if (!is_array($state)) {
            $state = [];
        }

        return array_merge([
            'stage' => (string) config('najm-hoda.runtime.autonomy.shadow_rollout.initial_stage', 'shadow'),
            'status' => 'active',
            'last_evaluated_at' => null,
            'last_report' => null,
            'last_decision' => null,
            'last_decision_reason' => null,
            'last_transition_at' => null,
            'last_transition_by' => null,
            'updated_at' => null,
        ], $state);
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
    public function evaluate(?int $windowHours = null, bool $dryRun = true): array
    {
        if (!(bool) config('najm-hoda.runtime.autonomy.shadow_rollout.enabled', true)) {
            return [
                'success' => false,
                'reason' => 'shadow_rollout_disabled',
            ];
        }

        $windowHours = max(1, min(720, (int) ($windowHours ?? config('najm-hoda.runtime.autonomy.shadow_rollout.window_hours', 24))));
        $state = $this->status();
        $stage = (string) ($state['stage'] ?? 'shadow');

        $evaluation = $this->evaluationService->run($windowHours, true);
        $codeOps = $this->codeOpsCanaryService->status();
        $operations = $this->operationsService->status();
        $alerts = $this->alertingService->evaluateAndAlert($windowHours, true);

        $report = [
            'stage' => $stage,
            'window_hours' => $windowHours,
            'evaluation_status' => (string) ($evaluation['status'] ?? 'unknown'),
            'decision_quality_score' => (float) data_get($evaluation, 'decision_quality.score', 0),
            'critical_alert_count' => count(array_filter((array) ($alerts['alerts'] ?? []), static function (array $row): bool {
                return (string) ($row['severity'] ?? '') === 'critical';
            })),
            'governance_alert_count' => (int) ($alerts['count'] ?? 0),
            'codeops_status' => (string) ($codeOps['status'] ?? 'unknown'),
            'operations_status' => (string) ($operations['status'] ?? 'inactive'),
            'operations_halted' => (string) ($operations['status'] ?? '') === 'halted',
            'can_advance' => false,
            'decision' => 'hold',
            'blocking_reasons' => [],
            'next_stage' => null,
            'generated_at' => now()->toIso8601String(),
        ];

        $rules = $this->stageRules($stage);
        $blocking = [];
        if ((string) ($report['evaluation_status'] ?? 'unknown') === 'breach') {
            $blocking[] = 'evaluation_breach';
        }
        if ((int) ($report['critical_alert_count'] ?? 0) > (int) ($rules['max_critical_alerts'] ?? 0)) {
            $blocking[] = 'critical_alert_threshold_exceeded';
        }
        if ((int) ($report['governance_alert_count'] ?? 0) > (int) ($rules['max_total_alerts'] ?? 0)) {
            $blocking[] = 'governance_alert_threshold_exceeded';
        }
        if ((float) ($report['decision_quality_score'] ?? 0) < (float) ($rules['decision_quality_min'] ?? 0)) {
            $blocking[] = 'decision_quality_below_threshold';
        }
        if (!(bool) ($rules['allow_non_active_ops'] ?? false) && (string) ($report['operations_status'] ?? '') !== 'active') {
            $blocking[] = 'operations_not_active';
        }
        if ((bool) ($report['operations_halted'] ?? false)) {
            $blocking[] = 'operations_halted';
        }
        if (in_array((string) ($report['codeops_status'] ?? ''), ['breach', 'rolled_back'], true)) {
            $blocking[] = 'codeops_unhealthy';
        }

        $nextStage = $this->nextStage($stage);
        $canAdvance = $nextStage !== null && empty($blocking);
        $decision = empty($blocking) ? ($nextStage !== null ? 'promote_ready' : 'stable_live') : 'hold';

        $report['can_advance'] = $canAdvance;
        $report['decision'] = $decision;
        $report['blocking_reasons'] = $blocking;
        $report['next_stage'] = $nextStage;

        if (!$dryRun) {
            $state['last_evaluated_at'] = now()->toIso8601String();
            $state['last_report'] = $report;
            $state['last_decision'] = $decision;
            $state['last_decision_reason'] = empty($blocking) ? 'guardrails_passed' : implode(',', $blocking);
            $state['updated_at'] = now()->toIso8601String();
            $this->storeState($state);

            $this->appendHistory([
                'type' => 'evaluation',
                'timestamp' => now()->toIso8601String(),
                'stage' => $stage,
                'decision' => $decision,
                'can_advance' => $canAdvance,
                'blocking_reasons' => $blocking,
            ]);

            $this->eventBus->emit('najm_hoda.autonomy.shadow_rollout.evaluated', [
                'stage' => $stage,
                'decision' => $decision,
                'can_advance' => $canAdvance,
                'next_stage' => $nextStage,
                'blocking_reasons' => $blocking,
            ]);
        }

        return [
            'success' => true,
            'state' => $this->status(),
            'report' => $report,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function advance(?int $byUserId = null, ?string $reason = null): array
    {
        $evaluation = $this->evaluate(null, false);
        if (!(bool) ($evaluation['success'] ?? false)) {
            return $evaluation;
        }

        $report = (array) ($evaluation['report'] ?? []);
        $state = (array) ($evaluation['state'] ?? $this->status());
        if (!(bool) ($report['can_advance'] ?? false)) {
            return [
                'success' => false,
                'reason' => 'guardrails_not_passed',
                'state' => $state,
                'report' => $report,
            ];
        }

        $nextStage = (string) ($report['next_stage'] ?? '');
        if ($nextStage === '') {
            return [
                'success' => false,
                'reason' => 'already_at_final_stage',
                'state' => $state,
                'report' => $report,
            ];
        }

        $previousStage = (string) ($state['stage'] ?? 'shadow');
        $state['stage'] = $nextStage;
        $state['last_transition_at'] = now()->toIso8601String();
        $state['last_transition_by'] = $byUserId;
        $state['last_decision'] = 'promoted';
        $state['last_decision_reason'] = $reason !== null && trim($reason) !== '' ? trim($reason) : 'guardrails_passed';
        $state['updated_at'] = now()->toIso8601String();
        $this->storeState($state);

        $row = [
            'type' => 'promoted',
            'timestamp' => now()->toIso8601String(),
            'from_stage' => $previousStage,
            'to_stage' => $nextStage,
            'by_user_id' => $byUserId,
            'reason' => $state['last_decision_reason'],
        ];
        $this->appendHistory($row);
        $this->eventBus->emit('najm_hoda.autonomy.shadow_rollout.promoted', $row);

        return [
            'success' => true,
            'state' => $state,
            'report' => $report,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function fallback(?int $byUserId = null, ?string $reason = null, ?string $stage = null): array
    {
        $targetStage = $this->normalizeStage($stage);
        if ($targetStage === null) {
            $targetStage = (string) config('najm-hoda.runtime.autonomy.shadow_rollout.initial_stage', 'shadow');
        }

        $state = $this->status();
        $previousStage = (string) ($state['stage'] ?? 'shadow');
        $state['stage'] = $targetStage;
        $state['last_transition_at'] = now()->toIso8601String();
        $state['last_transition_by'] = $byUserId;
        $state['last_decision'] = 'fallback';
        $state['last_decision_reason'] = $reason !== null && trim($reason) !== '' ? trim($reason) : 'manual_fallback';
        $state['updated_at'] = now()->toIso8601String();
        $this->storeState($state);

        $row = [
            'type' => 'fallback',
            'timestamp' => now()->toIso8601String(),
            'from_stage' => $previousStage,
            'to_stage' => $targetStage,
            'by_user_id' => $byUserId,
            'reason' => $state['last_decision_reason'],
        ];
        $this->appendHistory($row);
        $this->eventBus->emit('najm_hoda.autonomy.shadow_rollout.fallback', $row);

        return [
            'success' => true,
            'state' => $state,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function stageRules(string $stage): array
    {
        $default = [
            'decision_quality_min' => 0.65,
            'max_critical_alerts' => 0,
            'max_total_alerts' => 2,
            'allow_non_active_ops' => false,
        ];
        $rules = config("najm-hoda.runtime.autonomy.shadow_rollout.stages.{$stage}", []);
        if (!is_array($rules)) {
            $rules = [];
        }

        return array_merge($default, $rules);
    }

    protected function nextStage(string $stage): ?string
    {
        $ordered = $this->stageOrder();
        $idx = array_search($stage, $ordered, true);
        if ($idx === false) {
            return null;
        }

        $nextIndex = $idx + 1;
        if (!isset($ordered[$nextIndex])) {
            return null;
        }

        return (string) $ordered[$nextIndex];
    }

    /**
     * @return array<int, string>
     */
    protected function stageOrder(): array
    {
        $ordered = config('najm-hoda.runtime.autonomy.shadow_rollout.stage_order', [
            'shadow',
            'limited_live',
            'supervised_live',
            'autonomous_live',
        ]);

        if (!is_array($ordered) || empty($ordered)) {
            return ['shadow', 'limited_live', 'supervised_live', 'autonomous_live'];
        }

        $normalized = [];
        foreach ($ordered as $value) {
            $stage = $this->normalizeStage($value);
            if ($stage !== null && !in_array($stage, $normalized, true)) {
                $normalized[] = $stage;
            }
        }

        return !empty($normalized) ? $normalized : ['shadow', 'limited_live', 'supervised_live', 'autonomous_live'];
    }

    protected function normalizeStage(mixed $value): ?string
    {
        $value = strtolower(trim((string) $value));
        if ($value === '') {
            return null;
        }

        if (!in_array($value, ['shadow', 'limited_live', 'supervised_live', 'autonomous_live'], true)) {
            return null;
        }

        return $value;
    }

    /**
     * @param array<string, mixed> $state
     */
    protected function storeState(array $state): void
    {
        Cache::put(
            $this->stateKey,
            $state,
            now()->addMinutes(max(60, (int) config('najm-hoda.runtime.autonomy.shadow_rollout.retention_minutes', 20160)))
        );
    }

    /**
     * @param array<string, mixed> $row
     */
    protected function appendHistory(array $row): void
    {
        $rows = Cache::get($this->historyKey, []);
        if (!is_array($rows)) {
            $rows = [];
        }
        array_unshift($rows, $row);
        $rows = array_slice(
            $rows,
            0,
            max(20, (int) config('najm-hoda.runtime.autonomy.shadow_rollout.history_size', 500))
        );
        Cache::put(
            $this->historyKey,
            $rows,
            now()->addMinutes(max(60, (int) config('najm-hoda.runtime.autonomy.shadow_rollout.retention_minutes', 20160)))
        );
    }
}
