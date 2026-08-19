<?php

namespace App\Services\NajmHoda\FounderOps;

class FounderAttentionService
{
    public function __construct(
        protected FounderOperationsSnapshotService $snapshots,
        protected FounderApprovalInboxService $approvalInbox
    ) {}

    public function brief(int $hours = 24): array
    {
        $snapshot = $this->snapshots->snapshot($hours);
        $approvalSnapshot = $this->approvalInbox->snapshot();
        $items = [];

        $runtimeStatus = (string) data_get($snapshot, 'runtime_health.status', 'healthy');
        if ($runtimeStatus === 'critical') $items[] = $this->item('P0', 'runtime_health', 'Najm Hoda runtime is critical');
        elseif ($runtimeStatus === 'warning') $items[] = $this->item('P1', 'runtime_health', 'Najm Hoda runtime needs attention');

        $overdueFounderApprovals = (int) data_get($approvalSnapshot, 'overdue', 0);
        if ($overdueFounderApprovals > 0) {
            $items[] = $this->item('P1', 'founder_approvals', 'Founder action approvals are overdue', [
                'count' => $overdueFounderApprovals,
                'pending_total' => (int) data_get($approvalSnapshot, 'pending', 0),
            ]);
        }

        $rules = [
            ['P1','support','support.high_priority_active','High-priority support tickets are active'],
            ['P1','support','support.unassigned_active','Active support tickets have no assignee'],
            ['P1','governance','governance.overdue_open','Open elections are past their configured end time'],
            ['P1','reports_moderation','moderation.escalated_to_admin','Moderation reports are escalated to the central admin'],
            ['P1','stock','stock.expired_unsettled','Stock auctions expired without settled status'],
            ['P1','secretariat','secretariat.overdue_dispatches','Secretariat dispatches are overdue'],
            ['P1','najm_bahar','najm_bahar.scheduled_overdue','Najm Bahar scheduled transactions are overdue'],
            ['P2','governance','governance.ending_within_24h','Active elections are ending within 24 hours'],
            ['P2','reports_moderation','moderation.pending_group_manager','Reports are waiting for group-manager review'],
            ['P2','invitations','growth.pending_invitation_requests','Invitation requests are waiting for review'],
            ['P2','stock','stock.ending_within_24h','Running stock auctions are ending within 24 hours'],
            ['P2','secretariat','secretariat.dispatches_due_within_24h','Secretariat dispatches are due within 24 hours'],
            ['P2','secretariat','secretariat.responses_due','Secretariat dispatches are awaiting a response'],
            ['P2','najm_bahar','najm_bahar.projects_submitted','Najm Bahar projects are waiting for review'],
            ['P2','najm_bahar','najm_bahar.scheduled_due_within_24h','Najm Bahar scheduled transactions are due within 24 hours'],
            ['P2','content','content.faq_pending','FAQ questions are waiting for an answer'],
        ];
        foreach ($rules as [$priority, $domain, $path, $title]) {
            $count = (int) data_get($snapshot, $path, 0);
            if ($count > 0) $items[] = $this->item($priority, $domain, $title, ['count' => $count]);
        }

        $pendingFounderApprovals = (int) data_get($approvalSnapshot, 'pending', 0);
        if ($pendingFounderApprovals > 0 && $overdueFounderApprovals === 0) {
            $items[] = $this->item('P2', 'founder_approvals', 'Founder actions are waiting for explicit approval', [
                'count' => $pendingFounderApprovals,
                'by_risk' => data_get($approvalSnapshot, 'by_risk', []),
            ]);
        }

        $pendingApprovals = (int) data_get($snapshot, 'approvals.total', 0);
        if ($pendingApprovals > 0) $items[] = $this->item('P2', 'approvals', 'Reference-data approvals are waiting', [
            'count' => $pendingApprovals,
            'references' => data_get($snapshot, 'approvals.references.by_type', []),
            'locations' => data_get($snapshot, 'approvals.locations.by_type', []),
        ]);

        $sensitiveConfigChanges = collect((array) data_get($snapshot, 'recent_managed_events', []))
            ->filter(fn (array $event): bool => str_starts_with((string) ($event['event'] ?? ''), 'najm_hoda.input.admin_settings.'))->count();
        if ($sensitiveConfigChanges > 0) $items[] = $this->item('P2', 'admin_settings', 'Sensitive admin configuration changed in the reporting window', ['events' => $sensitiveConfigChanges]);

        foreach ([
            ['users','users.new_members','New members joined in the reporting window'],
            ['groups','groups.created_in_window','New system/community groups were created'],
            ['notifications','notifications.announcements_in_window','Announcements were published in the reporting window'],
            ['blog','blog.published_in_window','Blog posts were published in the reporting window'],
            ['invitations','growth.used_codes_in_window','Invitation codes converted to registrations'],
            ['najm_bahar','najm_bahar.review_events_in_window','Najm Bahar project-review events occurred in the reporting window'],
        ] as [$domain, $path, $title]) {
            $count = (int) data_get($snapshot, $path, 0);
            if ($count > 0) $items[] = $this->item('P3', $domain, $title, ['count' => $count]);
        }

        $rolloutQueue = (array) data_get($snapshot, 'management_coverage.next_domains', []);
        if ($rolloutQueue !== [] && is_array($rolloutQueue[0] ?? null)) {
            $next = $rolloutQueue[0];
            $items[] = $this->item('P3', 'management_coverage', 'Next management domain is ready for integration work', [
                'domain' => $next['key'] ?? null, 'label' => $next['label'] ?? null,
                'stage' => $next['integration_stage'] ?? null, 'risk' => $next['risk'] ?? null,
            ]);
        }

        usort($items, static function (array $a, array $b): int {
            $rank = ['P0' => 0, 'P1' => 1, 'P2' => 2, 'P3' => 3];
            return ($rank[$a['priority']] ?? 99) <=> ($rank[$b['priority']] ?? 99);
        });

        return [
            'generated_at' => data_get($snapshot, 'window.generated_at'),
            'summary' => [
                'total_attention_items' => count($items),
                'P0' => $this->countPriority($items, 'P0'), 'P1' => $this->countPriority($items, 'P1'),
                'P2' => $this->countPriority($items, 'P2'), 'P3' => $this->countPriority($items, 'P3'),
            ],
            'items' => $items,
            'founder_approvals' => $approvalSnapshot,
            'management_coverage' => data_get($snapshot, 'management_coverage', []),
        ];
    }

    protected function item(string $priority, string $domain, string $title, array $context = []): array
    {
        return ['priority' => $priority, 'domain' => $domain, 'title' => $title, 'context' => $context,
            'requires_founder_decision' => in_array($priority, ['P0', 'P1'], true) || $domain === 'founder_approvals'];
    }

    protected function countPriority(array $items, string $priority): int
    {
        return count(array_filter($items, static fn (array $item): bool => ($item['priority'] ?? null) === $priority));
    }
}
