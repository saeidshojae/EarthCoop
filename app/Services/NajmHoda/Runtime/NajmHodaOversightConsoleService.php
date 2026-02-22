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
        protected NajmHodaAdaptivePolicyLearningService $policyLearningService,
        protected NajmHodaSafeCodeOpsCanaryService $codeOpsCanaryService
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
        $delegationEvents = array_values(array_filter(
            $recentAutonomyEvents,
            static fn (array $entry): bool => str_starts_with((string) ($entry['event'] ?? ''), 'najm_hoda.autonomy.delegation.')
        ));
        $codeOpsCanary = $this->codeOpsCanaryService->status();
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
                'require_approval_count' => count(array_filter($delegations, static function (array $row): bool {
                    return (bool) ($row['require_approval'] ?? false);
                })),
                'expiring_soon_count' => $this->countExpiringSoon($delegations, 6),
                'recent_active' => $this->compactDelegations($delegations, min(20, $limit)),
                'event_summary' => $this->summarizeDelegationEvents($delegationEvents),
            ],
            'adaptive_policy' => [
                'current_override' => $this->policyLearningService->currentOverride(),
                'pending_recommendations' => $pendingPolicyRecommendations,
                'pending_count' => count($pendingPolicyRecommendations),
                'recent_evidence' => $policyEvidence,
            ],
            'codeops_canary' => $codeOpsCanary,
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

    /**
     * @param array<int, array<string, mixed>> $delegations
     */
    protected function countExpiringSoon(array $delegations, int $withinHours): int
    {
        $withinHours = max(1, $withinHours);
        $count = 0;
        foreach ($delegations as $row) {
            $expiresAt = (string) ($row['expires_at'] ?? '');
            if ($expiresAt === '') {
                continue;
            }

            try {
                $deadline = \Carbon\CarbonImmutable::parse($expiresAt);
                if ($deadline->isPast()) {
                    continue;
                }

                if (now()->diffInHours($deadline, false) <= $withinHours) {
                    $count++;
                }
            } catch (\Throwable) {
                continue;
            }
        }

        return $count;
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     * @return array<int, array<string, mixed>>
     */
    protected function compactDelegations(array $rows, int $limit): array
    {
        $limit = max(1, $limit);
        $data = array_map(static function (array $row): array {
            return [
                'id' => (string) ($row['id'] ?? ''),
                'principal_type' => (string) ($row['principal_type'] ?? ''),
                'principal_id' => (string) ($row['principal_id'] ?? ''),
                'action' => (string) ($row['action'] ?? ''),
                'scope' => (string) ($row['scope'] ?? 'global'),
                'require_approval' => (bool) ($row['require_approval'] ?? false),
                'created_at' => (string) ($row['created_at'] ?? ''),
                'expires_at' => (string) ($row['expires_at'] ?? ''),
                'reason' => (string) ($row['reason'] ?? ''),
            ];
        }, $rows);

        usort($data, static function (array $a, array $b): int {
            return strcmp((string) ($b['created_at'] ?? ''), (string) ($a['created_at'] ?? ''));
        });

        return array_slice($data, 0, $limit);
    }

    /**
     * @param array<int, array<string, mixed>> $events
     * @return array<string, mixed>
     */
    protected function summarizeDelegationEvents(array $events): array
    {
        $summary = [
            'granted' => 0,
            'authorized' => 0,
            'denied' => 0,
            'revoked' => 0,
            'expired' => 0,
            'denied_reasons' => [],
            'recent_denied' => [],
        ];

        foreach ($events as $entry) {
            $event = (string) ($entry['event'] ?? '');
            if ($event === 'najm_hoda.autonomy.delegation.granted') {
                $summary['granted']++;
            } elseif ($event === 'najm_hoda.autonomy.delegation.authorized') {
                $summary['authorized']++;
            } elseif ($event === 'najm_hoda.autonomy.delegation.denied') {
                $summary['denied']++;
                $reason = trim((string) data_get($entry, 'payload.reason', 'unknown'));
                if ($reason === '') {
                    $reason = 'unknown';
                }
                $summary['denied_reasons'][$reason] = (int) ($summary['denied_reasons'][$reason] ?? 0) + 1;
                $summary['recent_denied'][] = [
                    'actor_id' => data_get($entry, 'payload.actor_id'),
                    'action' => (string) data_get($entry, 'payload.action', ''),
                    'scope' => (string) data_get($entry, 'payload.scope', ''),
                    'reason' => $reason,
                    'timestamp' => (string) ($entry['timestamp'] ?? ''),
                ];
            } elseif ($event === 'najm_hoda.autonomy.delegation.revoked') {
                $summary['revoked']++;
            } elseif ($event === 'najm_hoda.autonomy.delegation.expired') {
                $summary['expired']++;
            }
        }

        arsort($summary['denied_reasons']);
        $summary['recent_denied'] = array_slice($summary['recent_denied'], 0, 10);

        return $summary;
    }
}
