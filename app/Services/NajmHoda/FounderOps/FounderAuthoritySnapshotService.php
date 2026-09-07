<?php

namespace App\Services\NajmHoda\FounderOps;

use App\Services\NajmHoda\Runtime\NajmHodaAutonomyApprovalService;

class FounderAuthoritySnapshotService
{
    public function __construct(
        protected FounderActionAuthorityService $authority,
        protected FounderDelegationGrantService $delegations,
        protected NajmHodaAutonomyApprovalService $approvals
    ) {}

    /** @return array<string,mixed> */
    public function snapshot(): array
    {
        $matrix = $this->authority->matrix();
        $counts = array_fill_keys(FounderActionAuthorityService::MODES, 0);
        $total = 0;
        foreach ($matrix as $actions) {
            foreach ($actions as $mode) {
                $total++;
                if (isset($counts[$mode])) $counts[$mode]++;
            }
        }

        $pending = $this->approvals->pending(200);
        $overdue = array_values(array_filter($pending, static fn (array $item): bool => ($item['sla_status'] ?? null) === 'overdue'));
        $activeDelegations = $this->delegations->active();

        return [
            'total_actions' => $total,
            'by_mode' => $counts,
            'default_mode' => (string) config('najm-hoda-founder-action-policy.default_mode', 'forbidden'),
            'fail_closed' => (string) config('najm-hoda-founder-action-policy.default_mode', 'forbidden') === 'forbidden',
            'pending_approvals_count' => count($pending),
            'overdue_approvals_count' => count($overdue),
            'active_delegations_count' => count($activeDelegations),
            'active_delegations' => array_map(static fn (array $grant): array => [
                'id' => $grant['id'] ?? null,
                'domain' => $grant['domain'] ?? null,
                'action' => $grant['action'] ?? null,
                'expires_at' => $grant['expires_at'] ?? null,
            ], $activeDelegations),
        ];
    }
}
