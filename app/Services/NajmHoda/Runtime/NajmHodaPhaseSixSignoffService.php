<?php

namespace App\Services\NajmHoda\Runtime;

use Illuminate\Support\Facades\Cache;

class NajmHodaPhaseSixSignoffService
{
    protected string $stateKey = 'najm_hoda:phase6:signoff:state';
    protected string $historyKey = 'najm_hoda:phase6:signoff:history';

    public function __construct(
        protected RuntimeEventBus $eventBus,
        protected NajmHodaProductionReadinessService $readinessService,
        protected NajmHodaShadowLiveRolloutService $rolloutService,
        protected NajmHodaContinuousEvaluationHarnessService $evaluationService,
        protected NajmHodaOperationalAutonomyActivationService $operationsService,
        protected NajmHodaSafeCodeOpsCanaryService $codeOpsCanaryService
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
            'last_report_at' => null,
            'last_decision' => null,
            'last_summary' => null,
            'last_signed_at' => null,
            'last_signed_by' => null,
            'last_signed_decision' => null,
            'last_signed_note' => null,
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
    public function report(?int $windowHours = null, bool $persist = true): array
    {
        $windowHours = max(1, min(720, (int) ($windowHours ?? config('najm-hoda.runtime.autonomy.readiness.window_hours', 24))));

        $readiness = $this->readinessService->review($windowHours);
        $rolloutState = $this->rolloutService->status();
        $rolloutEvaluation = $this->rolloutService->evaluate($windowHours, true);
        $evaluation = $this->evaluationService->lastReport();
        $operations = $this->operationsService->status();
        $codeOps = $this->codeOpsCanaryService->status();

        $decision = (string) ($readiness['decision'] ?? 'conditional_go');
        $rationale = [];
        if ($decision === 'no_go') {
            $rationale[] = 'readiness_blockers_present';
        } elseif ($decision === 'conditional_go') {
            $rationale[] = 'readiness_has_warnings';
        }

        $requireFinalStage = (bool) config('najm-hoda.runtime.autonomy.phase6_signoff.require_autonomous_live_stage', true);
        $rolloutStage = (string) ($rolloutState['stage'] ?? 'shadow');
        if ($requireFinalStage && $rolloutStage !== 'autonomous_live') {
            if ($decision === 'go') {
                $decision = 'conditional_go';
            }
            $rationale[] = 'rollout_not_at_autonomous_live';
        }

        if ((string) ($operations['status'] ?? 'inactive') === 'halted') {
            $decision = 'no_go';
            $rationale[] = 'operations_24x7_halted';
        }
        if ((string) ($codeOps['status'] ?? 'unknown') === 'breach') {
            $decision = 'no_go';
            $rationale[] = 'codeops_breach';
        }
        if ((string) ($evaluation['status'] ?? 'unknown') === 'breach') {
            $decision = 'no_go';
            $rationale[] = 'continuous_evaluation_breach';
        }

        $report = [
            'generated_at' => now()->toIso8601String(),
            'window_hours' => $windowHours,
            'decision' => $decision,
            'rationale' => array_values(array_unique($rationale)),
            'readiness' => [
                'decision' => (string) ($readiness['decision'] ?? 'conditional_go'),
                'blocker_count' => (int) ($readiness['blocker_count'] ?? 0),
                'warning_count' => (int) ($readiness['warning_count'] ?? 0),
            ],
            'rollout' => [
                'stage' => $rolloutStage,
                'last_decision' => (string) ($rolloutState['last_decision'] ?? ''),
                'guardrail_decision' => (string) data_get($rolloutEvaluation, 'report.decision', 'hold'),
                'can_advance' => (bool) data_get($rolloutEvaluation, 'report.can_advance', false),
            ],
            'operations_24x7' => [
                'status' => (string) ($operations['status'] ?? 'inactive'),
                'last_tick_status' => (string) ($operations['last_tick_status'] ?? ''),
            ],
            'continuous_evaluation' => [
                'status' => (string) ($evaluation['status'] ?? 'unknown'),
                'alert_count' => (int) ($evaluation['alert_count'] ?? 0),
                'decision_quality_score' => (float) data_get($evaluation, 'decision_quality.score', 0),
            ],
            'codeops' => [
                'status' => (string) ($codeOps['status'] ?? 'unknown'),
                'phase_percent' => $codeOps['phase_percent'] ?? null,
            ],
            'artifacts' => [
                'readiness_integrity_hash' => (string) data_get($readiness, 'evidence.integrity_hash', ''),
                'readiness_generated_at' => (string) ($readiness['generated_at'] ?? ''),
            ],
        ];

        if ($persist) {
            $state = $this->status();
            $state['last_report_at'] = now()->toIso8601String();
            $state['last_decision'] = $decision;
            $state['last_summary'] = $report;
            $state['updated_at'] = now()->toIso8601String();
            $this->storeState($state);

            $this->appendHistory([
                'type' => 'report',
                'timestamp' => now()->toIso8601String(),
                'decision' => $decision,
                'rationale' => $report['rationale'],
            ]);

            $this->eventBus->emit('najm_hoda.autonomy.phase6.signoff.report_generated', [
                'decision' => $decision,
                'window_hours' => $windowHours,
                'rationale' => $report['rationale'],
            ]);
        }

        return $report;
    }

    /**
     * @return array<string, mixed>
     */
    public function sign(string $decision, ?int $byUserId = null, ?string $note = null, ?int $windowHours = null): array
    {
        $decision = strtolower(trim($decision));
        if (!in_array($decision, ['go', 'conditional_go', 'no_go'], true)) {
            return [
                'success' => false,
                'reason' => 'invalid_decision',
            ];
        }

        $report = $this->report($windowHours, true);
        $state = $this->status();
        $state['last_signed_at'] = now()->toIso8601String();
        $state['last_signed_by'] = $byUserId;
        $state['last_signed_decision'] = $decision;
        $state['last_signed_note'] = $note !== null ? trim($note) : null;
        $state['updated_at'] = now()->toIso8601String();
        $this->storeState($state);

        $row = [
            'type' => 'signoff',
            'timestamp' => now()->toIso8601String(),
            'decision' => $decision,
            'report_decision' => (string) ($report['decision'] ?? 'conditional_go'),
            'by_user_id' => $byUserId,
            'note' => $note !== null ? trim($note) : null,
        ];
        $this->appendHistory($row);
        $this->eventBus->emit('najm_hoda.autonomy.phase6.signoff.recorded', $row);

        return [
            'success' => true,
            'state' => $state,
            'report' => $report,
        ];
    }

    /**
     * @param array<string, mixed> $state
     */
    protected function storeState(array $state): void
    {
        Cache::put(
            $this->stateKey,
            $state,
            now()->addMinutes(max(60, (int) config('najm-hoda.runtime.autonomy.phase6_signoff.retention_minutes', 43200)))
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
            max(20, (int) config('najm-hoda.runtime.autonomy.phase6_signoff.history_size', 500))
        );
        Cache::put(
            $this->historyKey,
            $rows,
            now()->addMinutes(max(60, (int) config('najm-hoda.runtime.autonomy.phase6_signoff.retention_minutes', 43200)))
        );
    }
}
