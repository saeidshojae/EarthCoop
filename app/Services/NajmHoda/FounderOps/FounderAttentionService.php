<?php

namespace App\Services\NajmHoda\FounderOps;

class FounderAttentionService
{
    public function __construct(protected FounderOperationsSnapshotService $snapshots) {}

    public function brief(int $hours = 24): array
    {
        $snapshot = $this->snapshots->snapshot($hours);
        $items = [];

        $runtimeStatus = (string) data_get($snapshot, 'runtime_health.status', 'healthy');
        if ($runtimeStatus === 'critical') {
            $items[] = $this->item('P0', 'runtime_health', 'Najm Hoda runtime is critical', [
                'error_rate_percent' => data_get($snapshot, 'runtime_health.metrics.error_rate_percent'),
                'unresolved_requests' => data_get($snapshot, 'runtime_health.metrics.unresolved_requests'),
            ]);
        } elseif ($runtimeStatus === 'warning') {
            $items[] = $this->item('P1', 'runtime_health', 'Najm Hoda runtime needs attention');
        }

        $highPriorityTickets = (int) data_get($snapshot, 'support.high_priority_active', 0);
        if ($highPriorityTickets > 0) $items[] = $this->item('P1', 'support', 'High-priority support tickets are active', ['count' => $highPriorityTickets]);

        $unassignedTickets = (int) data_get($snapshot, 'support.unassigned_active', 0);
        if ($unassignedTickets > 0) $items[] = $this->item('P1', 'support', 'Active support tickets have no assignee', ['count' => $unassignedTickets]);

        $overdueElections = (int) data_get($snapshot, 'governance.overdue_open', 0);
        if ($overdueElections > 0) $items[] = $this->item('P1', 'governance', 'Open elections are past their configured end time', ['count' => $overdueElections]);

        $adminEscalations = (int) data_get($snapshot, 'moderation.escalated_to_admin', 0);
        if ($adminEscalations > 0) $items[] = $this->item('P1', 'reports_moderation', 'Moderation reports are escalated to the central admin', ['count' => $adminEscalations]);

        $expiredUnsettled = (int) data_get($snapshot, 'stock.expired_unsettled', 0);
        if ($expiredUnsettled > 0) $items[] = $this->item('P1', 'stock', 'Stock auctions expired without settled status', ['count' => $expiredUnsettled]);

        $endingElections = (int) data_get($snapshot, 'governance.ending_within_24h', 0);
        if ($endingElections > 0) $items[] = $this->item('P2', 'governance', 'Active elections are ending within 24 hours', ['count' => $endingElections]);

        $pendingManagerReports = (int) data_get($snapshot, 'moderation.pending_group_manager', 0);
        if ($pendingManagerReports > 0) $items[] = $this->item('P2', 'reports_moderation', 'Reports are waiting for group-manager review', ['count' => $pendingManagerReports]);

        $pendingApprovals = (int) data_get($snapshot, 'approvals.total', 0);
        if ($pendingApprovals > 0) {
            $items[] = $this->item('P2', 'approvals', 'Reference-data approvals are waiting', [
                'count' => $pendingApprovals,
                'references' => data_get($snapshot, 'approvals.references.by_type', []),
                'locations' => data_get($snapshot, 'approvals.locations.by_type', []),
            ]);
        }

        $pendingInvitations = (int) data_get($snapshot, 'growth.pending_invitation_requests', 0);
        if ($pendingInvitations > 0) $items[] = $this->item('P2', 'invitations', 'Invitation requests are waiting for review', ['count' => $pendingInvitations]);

        $endingAuctions = (int) data_get($snapshot, 'stock.ending_within_24h', 0);
        if ($endingAuctions > 0) $items[] = $this->item('P2', 'stock', 'Running stock auctions are ending within 24 hours', ['count' => $endingAuctions]);

        $sensitiveConfigChanges = collect((array) data_get($snapshot, 'recent_managed_events', []))
            ->filter(fn (array $event): bool => str_starts_with((string) ($event['event'] ?? ''), 'najm_hoda.input.admin_settings.'))
            ->count();
        if ($sensitiveConfigChanges > 0) $items[] = $this->item('P2', 'admin_settings', 'Sensitive admin configuration changed in the reporting window', ['events' => $sensitiveConfigChanges]);

        $newMembers = (int) data_get($snapshot, 'users.new_members', 0);
        if ($newMembers > 0) $items[] = $this->item('P3', 'users', 'New members joined in the reporting window', [
            'count' => $newMembers, 'verified' => (int) data_get($snapshot, 'users.new_verified_members', 0),
        ]);

        $newGroups = (int) data_get($snapshot, 'groups.created_in_window', 0);
        if ($newGroups > 0) $items[] = $this->item('P3', 'groups', 'New system/community groups were created', ['count' => $newGroups]);

        $announcements = (int) data_get($snapshot, 'notifications.announcements_in_window', 0);
        if ($announcements > 0) $items[] = $this->item('P3', 'notifications', 'Announcements were published in the reporting window', [
            'count' => $announcements, 'pinned' => (int) data_get($snapshot, 'notifications.pinned_announcements_in_window', 0),
        ]);

        $blogPublished = (int) data_get($snapshot, 'blog.published_in_window', 0);
        if ($blogPublished > 0) $items[] = $this->item('P3', 'blog', 'Blog posts were published in the reporting window', ['count' => $blogPublished]);

        $usedInvites = (int) data_get($snapshot, 'growth.used_codes_in_window', 0);
        if ($usedInvites > 0) $items[] = $this->item('P3', 'invitations', 'Invitation codes converted to registrations', ['count' => $usedInvites]);

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
            'management_coverage' => data_get($snapshot, 'management_coverage', []),
        ];
    }

    protected function item(string $priority, string $domain, string $title, array $context = []): array
    {
        return [
            'priority' => $priority, 'domain' => $domain, 'title' => $title, 'context' => $context,
            'requires_founder_decision' => in_array($priority, ['P0', 'P1'], true),
        ];
    }

    protected function countPriority(array $items, string $priority): int
    {
        return count(array_filter($items, static fn (array $item): bool => ($item['priority'] ?? null) === $priority));
    }
}
