<?php

namespace App\Services\NajmHoda\Runtime;

use App\Models\NajmHodaRuntimeEvent;
use Throwable;

class NajmHodaComplianceEvidenceService
{
    public function __construct(
        protected NajmHodaAutonomyAuditService $auditService,
        protected NajmHodaAutonomyApprovalService $approvalService,
        protected NajmHodaGovernanceAlertingService $alertingService,
        protected NajmHodaAutonomyGameDayService $gameDayService
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function buildPack(?int $windowHours = null): array
    {
        $windowHours = $windowHours ?? (int) config('najm-hoda.runtime.autonomy.compliance.window_hours', 24);
        $windowHours = max(1, min(24 * 30, $windowHours));

        $auditLimit = max(10, (int) config('najm-hoda.runtime.autonomy.compliance.audit_limit', 200));
        $approvalLimit = max(10, (int) config('najm-hoda.runtime.autonomy.compliance.approval_limit', 200));
        $alertsLimit = max(10, (int) config('najm-hoda.runtime.autonomy.compliance.alerts_limit', 200));
        $gamedayLimit = max(5, (int) config('najm-hoda.runtime.autonomy.compliance.gameday_limit', 50));
        $eventsLimit = max(20, (int) config('najm-hoda.runtime.autonomy.compliance.events_limit', 500));

        $audit = $this->auditService->history($auditLimit);
        $approvals = $this->approvalService->history($approvalLimit);
        $alerts = $this->alertingService->history($alertsLimit);
        $gameday = $this->gameDayService->history($gamedayLimit);
        $events = $this->recentAutonomyEvents($windowHours, $eventsLimit);

        $pack = [
            'meta' => [
                'generated_at' => now()->toIso8601String(),
                'window_hours' => $windowHours,
                'schema_version' => 'v1',
            ],
            'summary' => [
                'audit_traces' => count($audit),
                'approval_requests' => count($approvals),
                'approval_pending' => count(array_filter($approvals, static fn (array $r): bool => (string) ($r['status'] ?? '') === 'pending')),
                'approval_rejected' => count(array_filter($approvals, static fn (array $r): bool => (string) ($r['status'] ?? '') === 'rejected')),
                'governance_alerts' => count($alerts),
                'gameday_reports' => count($gameday),
                'runtime_events' => count($events),
            ],
            'audit_traces' => $audit,
            'approvals' => $approvals,
            'governance_alerts' => $alerts,
            'gameday_reports' => $gameday,
            'runtime_events' => $events,
        ];

        $json = json_encode($pack, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $pack['meta']['integrity_hash'] = hash('sha256', $json ?: '{}');

        return $pack;
    }

    public function exportJson(?int $windowHours = null): string
    {
        $pack = $this->buildPack($windowHours);
        return (string) json_encode($pack, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function recentAutonomyEvents(int $windowHours, int $limit): array
    {
        try {
            $cutoff = now()->subHours($windowHours);

            return NajmHodaRuntimeEvent::query()
                ->where('event', 'like', 'najm_hoda.autonomy.%')
                ->where('created_at', '>=', $cutoff)
                ->latest('id')
                ->limit($limit)
                ->get()
                ->map(static function (NajmHodaRuntimeEvent $entry): array {
                    return [
                        'event' => (string) $entry->event,
                        'request_id' => $entry->request_id !== null ? (string) $entry->request_id : null,
                        'payload' => is_array($entry->payload) ? $entry->payload : [],
                        'created_at' => optional($entry->created_at)->toIso8601String(),
                    ];
                })
                ->all();
        } catch (Throwable) {
            return [];
        }
    }
}
