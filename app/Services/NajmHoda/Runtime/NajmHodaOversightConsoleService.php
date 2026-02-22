<?php

namespace App\Services\NajmHoda\Runtime;

class NajmHodaOversightConsoleService
{
    public function __construct(
        protected RuntimeEventBus $eventBus,
        protected NajmHodaAutonomyApprovalService $approvalService,
        protected NajmHodaAutonomyControlService $controlService,
        protected NajmHodaAutonomyAuditService $auditService,
        protected NajmHodaDelegatedPermissionService $delegationService,
        protected NajmHodaAdaptivePolicyLearningService $policyLearningService
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function snapshot(int $limit = 50): array
    {
        $limit = max(10, min(300, $limit));
        $eventLimit = max(50, $limit * 5);

        $pendingApprovals = $this->approvalService->pending($limit);
        $auditHistory = $this->auditService->history($limit);
        $recentAutonomyEvents = array_values(array_filter(
            $this->eventBus->recent(null, $eventLimit),
            static fn (array $entry): bool => str_starts_with((string) ($entry['event'] ?? ''), 'najm_hoda.autonomy.')
        ));
        $recentAutonomyEvents = array_slice($recentAutonomyEvents, 0, $limit);

        $controlState = $this->controlService->state();
        $killSwitch = $this->controlService->killSwitchState();
        $override = $this->controlService->override();
        $delegations = $this->delegationService->listActive(null, null);
        $pendingPolicyRecommendations = $this->policyLearningService->listRecommendations('pending', $limit);
        $policyEvidence = $this->policyLearningService->recentEvidence(min(100, $limit));

        return [
            'generated_at' => now()->toIso8601String(),
            'controls' => [
                'state' => $controlState,
                'kill_switch' => $killSwitch,
                'override' => $override,
            ],
            'approvals' => [
                'pending' => $pendingApprovals,
                'pending_count' => count($pendingApprovals),
                'overdue_count' => count(array_filter($pendingApprovals, static function (array $row): bool {
                    return (string) ($row['sla_status'] ?? '') === 'overdue';
                })),
            ],
            'delegation' => [
                'active_count' => count($delegations),
                'by_principal_type' => $this->countBy($delegations, 'principal_type'),
                'by_action' => $this->countBy($delegations, 'action'),
            ],
            'adaptive_policy' => [
                'current_override' => $this->policyLearningService->currentOverride(),
                'pending_recommendations' => $pendingPolicyRecommendations,
                'pending_count' => count($pendingPolicyRecommendations),
                'recent_evidence' => $policyEvidence,
            ],
            'audit' => [
                'recent' => $auditHistory,
                'recent_count' => count($auditHistory),
                'failed_count' => count(array_filter($auditHistory, static function (array $trace): bool {
                    return (string) ($trace['status'] ?? '') === 'failed';
                })),
            ],
            'events' => [
                'recent' => $recentAutonomyEvents,
                'recent_count' => count($recentAutonomyEvents),
                'risk_signals' => $this->riskSignals($recentAutonomyEvents),
            ],
            'recommended_actions' => $this->buildRecommendations(
                $pendingApprovals,
                $controlState,
                $killSwitch,
                $auditHistory,
                $recentAutonomyEvents
            ),
        ];
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     * @return array<string, int>
     */
    protected function countBy(array $rows, string $field): array
    {
        $counts = [];
        foreach ($rows as $row) {
            $key = trim((string) ($row[$field] ?? 'unknown'));
            if ($key === '') {
                $key = 'unknown';
            }
            $counts[$key] = (int) ($counts[$key] ?? 0) + 1;
        }

        arsort($counts);
        return $counts;
    }

    /**
     * @param array<int, array<string, mixed>> $events
     * @return array<string, int>
     */
    protected function riskSignals(array $events): array
    {
        $signals = [
            'approval_requested' => 0,
            'approval_rejected' => 0,
            'delegation_denied' => 0,
            'chain_failed' => 0,
        ];

        foreach ($events as $event) {
            $name = (string) ($event['event'] ?? '');
            if ($name === 'najm_hoda.autonomy.approval.requested') {
                $signals['approval_requested']++;
            } elseif ($name === 'najm_hoda.autonomy.approval.decided' && (string) data_get($event, 'payload.decision') === 'reject') {
                $signals['approval_rejected']++;
            } elseif ($name === 'najm_hoda.autonomy.delegation.denied') {
                $signals['delegation_denied']++;
            } elseif ($name === 'najm_hoda.autonomy.orchestrator.chain.failed') {
                $signals['chain_failed']++;
            }
        }

        return $signals;
    }

    /**
     * @param array<int, array<string, mixed>> $pendingApprovals
     * @param array<string, mixed> $controlState
     * @param array<string, mixed> $killSwitch
     * @param array<int, array<string, mixed>> $auditHistory
     * @param array<int, array<string, mixed>> $events
     * @return array<int, array<string, mixed>>
     */
    protected function buildRecommendations(
        array $pendingApprovals,
        array $controlState,
        array $killSwitch,
        array $auditHistory,
        array $events
    ): array {
        $items = [];
        $overdueApprovals = count(array_filter($pendingApprovals, static function (array $row): bool {
            return (string) ($row['sla_status'] ?? '') === 'overdue';
        }));
        if ($overdueApprovals > 0) {
            $items[] = [
                'priority' => 'high',
                'type' => 'approval_backlog',
                'reason' => 'overdue_approval_requests',
                'count' => $overdueApprovals,
                'action' => 'review_or_veto_pending_approvals',
            ];
        }

        if ((string) ($controlState['status'] ?? '') === 'paused') {
            $items[] = [
                'priority' => 'high',
                'type' => 'autonomy_paused',
                'reason' => 'runtime_paused_by_operator',
                'action' => 'confirm_pause_reason_or_resume',
            ];
        }

        if ((bool) ($killSwitch['active'] ?? false)) {
            $items[] = [
                'priority' => 'critical',
                'type' => 'kill_switch_active',
                'reason' => 'global_kill_switch_is_on',
                'action' => 'investigate_incident_then_deactivate_kill_switch',
            ];
        }

        $failedRuns = count(array_filter($auditHistory, static function (array $trace): bool {
            return (string) ($trace['status'] ?? '') === 'failed';
        }));
        if ($failedRuns > 0) {
            $items[] = [
                'priority' => 'medium',
                'type' => 'failed_runs',
                'reason' => 'recent_orchestration_failures_detected',
                'count' => $failedRuns,
                'action' => 'review_audit_traces_and_adjust_policies',
            ];
        }

        $delegationDenied = count(array_filter($events, static function (array $event): bool {
            return (string) ($event['event'] ?? '') === 'najm_hoda.autonomy.delegation.denied';
        }));
        if ($delegationDenied > 0) {
            $items[] = [
                'priority' => 'medium',
                'type' => 'delegation_denied',
                'reason' => 'actions_blocked_by_missing_delegation',
                'count' => $delegationDenied,
                'action' => 'review_grants_or_adjust_action_scope',
            ];
        }

        if (empty($items)) {
            $items[] = [
                'priority' => 'low',
                'type' => 'healthy',
                'reason' => 'no_critical_oversight_signal_detected',
                'action' => 'continue_monitoring',
            ];
        }

        return $items;
    }
}
