<?php

namespace App\Services\NajmHoda\FounderOps;

class FounderAttentionService
{
    public function __construct(
        protected FounderOperationsSnapshotService $snapshots
    ) {
    }

    /**
     * Convert the full operational snapshot into a short founder attention queue.
     * This service never executes domain mutations; it only classifies attention.
     *
     * @return array<string, mixed>
     */
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
            $items[] = $this->item('P1', 'runtime_health', 'Najm Hoda runtime needs attention', [
                'error_rate_percent' => data_get($snapshot, 'runtime_health.metrics.error_rate_percent'),
                'unresolved_requests' => data_get($snapshot, 'runtime_health.metrics.unresolved_requests'),
            ]);
        }

        $highPriorityTickets = (int) data_get($snapshot, 'support.high_priority_active', 0);
        if ($highPriorityTickets > 0) {
            $items[] = $this->item('P1', 'support', 'High-priority support tickets are active', [
                'count' => $highPriorityTickets,
            ]);
        }

        $unassignedTickets = (int) data_get($snapshot, 'support.unassigned_active', 0);
        if ($unassignedTickets > 0) {
            $items[] = $this->item('P1', 'support', 'Active support tickets have no assignee', [
                'count' => $unassignedTickets,
            ]);
        }

        $pendingApprovals = (int) data_get($snapshot, 'approvals.total', 0);
        if ($pendingApprovals > 0) {
            $items[] = $this->item('P2', 'approvals', 'Reference-data approvals are waiting', [
                'count' => $pendingApprovals,
                'references' => data_get($snapshot, 'approvals.references.by_type', []),
                'locations' => data_get($snapshot, 'approvals.locations.by_type', []),
            ]);
        }

        $newMembers = (int) data_get($snapshot, 'users.new_members', 0);
        if ($newMembers > 0) {
            $items[] = $this->item('P3', 'users', 'New members joined in the reporting window', [
                'count' => $newMembers,
                'verified' => (int) data_get($snapshot, 'users.new_verified_members', 0),
            ]);
        }

        $rolloutQueue = (array) data_get($snapshot, 'management_coverage.next_domains', []);
        if ($rolloutQueue !== []) {
            $next = $rolloutQueue[0] ?? null;
            if (is_array($next)) {
                $items[] = $this->item('P3', 'management_coverage', 'Next management domain is ready for integration work', [
                    'domain' => $next['key'] ?? null,
                    'label' => $next['label'] ?? null,
                    'stage' => $next['integration_stage'] ?? null,
                    'risk' => $next['risk'] ?? null,
                ]);
            }
        }

        usort($items, static function (array $a, array $b): int {
            $rank = ['P0' => 0, 'P1' => 1, 'P2' => 2, 'P3' => 3];
            return ($rank[$a['priority']] ?? 99) <=> ($rank[$b['priority']] ?? 99);
        });

        return [
            'generated_at' => data_get($snapshot, 'window.generated_at'),
            'summary' => [
                'total_attention_items' => count($items),
                'P0' => $this->countPriority($items, 'P0'),
                'P1' => $this->countPriority($items, 'P1'),
                'P2' => $this->countPriority($items, 'P2'),
                'P3' => $this->countPriority($items, 'P3'),
            ],
            'items' => $items,
            'management_coverage' => data_get($snapshot, 'management_coverage', []),
        ];
    }

    protected function item(string $priority, string $domain, string $title, array $context = []): array
    {
        return [
            'priority' => $priority,
            'domain' => $domain,
            'title' => $title,
            'context' => $context,
            'requires_founder_decision' => in_array($priority, ['P0', 'P1'], true),
        ];
    }

    protected function countPriority(array $items, string $priority): int
    {
        return count(array_filter($items, static fn (array $item): bool => ($item['priority'] ?? null) === $priority));
    }
}
