<?php

namespace App\Services\NajmHoda\Runtime;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class NajmHodaMultiHorizonGoalEngineService
{
    public function __construct(
        protected RuntimeEventBus $eventBus,
        protected NajmHodaEventCoverageKpiService $coverageKpiService,
        protected NajmHodaUnifiedDomainKnowledgeGraphService $graphService
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function buildBacklog(array $query = []): array
    {
        $windowHours = max(1, (int) ($query['window_hours'] ?? config('najm-hoda.runtime.multi_horizon_goals.window_hours', 24)));
        $eventLimit = max(100, (int) ($query['event_limit'] ?? config('najm-hoda.runtime.multi_horizon_goals.event_limit', 2000)));
        $scope = trim((string) ($query['scope'] ?? 'global'));
        $actorId = isset($query['actor_id']) ? (int) $query['actor_id'] : null;

        $kpi = $this->coverageKpiService->snapshot($windowHours, $eventLimit, false);
        $graph = $this->graphService->query([
            'scope' => $scope,
            'actor_id' => $actorId,
            'limit' => min(100, max(20, (int) floor($eventLimit / 20))),
            'profile' => 'ops_triage',
        ]);

        $backlog = [];
        $horizons = [
            'daily' => [],
            'weekly' => [],
            'monthly' => [],
        ];

        $criticalCoverage = (float) data_get($kpi, 'metrics.critical_path_coverage', 0.0);
        if ($criticalCoverage < (float) config('najm-hoda.runtime.multi_horizon_goals.thresholds.critical_path_coverage_min', 0.95)) {
            $horizons['daily'][] = 'raise_critical_coverage';
            $horizons['weekly'][] = 'close_domain_instrumentation_gaps';
            $backlog[] = $this->task(
                'high',
                'daily',
                'recover_critical_coverage',
                'Coverage gap detected in critical event families',
                'coverage_kpi.critical_path_coverage'
            );
        }

        $unknownRisk = (float) data_get($kpi, 'metrics.unknown_risk_ratio', 1.0);
        if ($unknownRisk > (float) config('najm-hoda.runtime.multi_horizon_goals.thresholds.unknown_risk_ratio_max', 0.02)) {
            $horizons['daily'][] = 'enforce_event_risk_labeling';
            $backlog[] = $this->task(
                'high',
                'daily',
                'normalize_runtime_risk_labels',
                'Unknown risk ratio exceeded threshold',
                'coverage_kpi.unknown_risk_ratio'
            );
        }

        $supportCandidates = (array) data_get($graph, 'patterns.support_escalation_candidates', []);
        foreach ($supportCandidates as $candidate) {
            $ticketId = (int) ($candidate['ticket_id'] ?? 0);
            if ($ticketId <= 0) {
                continue;
            }
            $horizons['daily'][] = 'reduce_support_escalation_backlog';
            $backlog[] = $this->task(
                'high',
                'daily',
                "escalate_support_ticket_{$ticketId}",
                "Escalate support ticket {$ticketId} for manual triage",
                'graph.patterns.support_escalation_candidates'
            );
        }

        $projectHotspots = (array) data_get($graph, 'patterns.project_delivery_risk_hotspots', []);
        if (!empty($projectHotspots)) {
            $horizons['weekly'][] = 'stabilize_project_delivery_pipeline';
        }
        foreach ($projectHotspots as $hotspot) {
            $projectId = (int) ($hotspot['project_id'] ?? 0);
            if ($projectId <= 0) {
                continue;
            }
            $backlog[] = $this->task(
                'medium',
                'weekly',
                "review_project_delivery_{$projectId}",
                "Review project {$projectId} delivery failures and dependencies",
                'graph.patterns.project_delivery_risk_hotspots'
            );
        }

        $opsChains = (array) data_get($graph, 'patterns.ops_alert_chains', []);
        if (!empty($opsChains)) {
            $horizons['daily'][] = 'contain_operational_alert_chains';
            $horizons['weekly'][] = 'reduce_repeat_ops_incidents';
        }
        foreach ($opsChains as $chain) {
            $corr = trim((string) ($chain['correlation_id'] ?? ''));
            $key = $corr !== '' ? Str::slug($corr, '_') : Str::uuid()->toString();
            $backlog[] = $this->task(
                'high',
                'daily',
                "open_ops_incident_{$key}",
                'Open incident review for correlated ops/autonomy alerts',
                'graph.patterns.ops_alert_chains'
            );
        }

        $horizons['monthly'][] = 'improve_autonomy_reliability_slo';
        $horizons['monthly'][] = 'optimize_delegation_and_policy_coverage';

        $horizons = array_map(static fn (array $items): array => array_values(array_unique($items)), $horizons);
        $backlog = $this->prioritize($backlog);

        $result = [
            'generated_at' => now()->toIso8601String(),
            'window_hours' => $windowHours,
            'event_limit' => $eventLimit,
            'scope' => $scope,
            'actor_id' => $actorId,
            'kpi' => [
                'critical_path_coverage' => $criticalCoverage,
                'unknown_risk_ratio' => $unknownRisk,
                'status' => (string) data_get($kpi, 'evaluation.overall', 'unknown'),
            ],
            'horizons' => $horizons,
            'backlog' => $backlog,
        ];

        $ttlMinutes = max(30, (int) config('najm-hoda.runtime.multi_horizon_goals.snapshot_ttl_minutes', 180));
        Cache::put('najm_hoda:multi_horizon_goals:last_snapshot', $result, now()->addMinutes($ttlMinutes));

        $this->eventBus->emit('najm_hoda.autonomy.multi_horizon_goals.generated', [
            'scope' => $scope,
            'actor_id' => $actorId,
            'backlog_count' => count($backlog),
            'daily_goal_count' => count((array) ($horizons['daily'] ?? [])),
            'weekly_goal_count' => count((array) ($horizons['weekly'] ?? [])),
            'monthly_goal_count' => count((array) ($horizons['monthly'] ?? [])),
            'critical_path_coverage' => $criticalCoverage,
            'unknown_risk_ratio' => $unknownRisk,
            'risk' => !empty($opsChains) ? 'medium' : 'low',
        ]);

        return $result;
    }

    /**
     * @return array<string, mixed>
     */
    protected function task(string $priority, string $horizon, string $key, string $title, string $trigger): array
    {
        return [
            'id' => $key,
            'priority' => $priority,
            'horizon' => $horizon,
            'title' => $title,
            'trigger' => $trigger,
            'mode' => 'propose',
        ];
    }

    /**
     * @param array<int, array<string, mixed>> $tasks
     * @return array<int, array<string, mixed>>
     */
    protected function prioritize(array $tasks): array
    {
        $weights = ['high' => 3, 'medium' => 2, 'low' => 1];
        usort($tasks, static function (array $left, array $right) use ($weights): int {
            $lw = (int) ($weights[(string) ($left['priority'] ?? 'low')] ?? 1);
            $rw = (int) ($weights[(string) ($right['priority'] ?? 'low')] ?? 1);
            if ($lw === $rw) {
                return strcmp((string) ($left['id'] ?? ''), (string) ($right['id'] ?? ''));
            }
            return $rw <=> $lw;
        });

        return $tasks;
    }
}

