<?php

namespace App\Services\NajmHoda\FounderOps;

class FounderAuthoritySnapshotService
{
    public function __construct(protected FounderActionAuthorityService $authority) {}

    /** @return array<string,mixed> */
    public function snapshot(): array
    {
        $matrix = $this->authority->matrix();
        $counts = array_fill_keys(FounderActionAuthorityService::MODES, 0);
        $activeDelegations = [];
        $total = 0;

        foreach ($matrix as $domain => $actions) {
            foreach ($actions as $action => $mode) {
                $total++;
                $counts[$mode] = ($counts[$mode] ?? 0) + 1;
                if ($mode === 'delegated_safe') {
                    $decision = $this->authority->evaluate($domain, $action);
                    if ((bool) ($decision['may_execute'] ?? false)) {
                        $activeDelegations[] = $domain . '.' . $action;
                    }
                }
            }
        }

        return [
            'total_actions' => $total,
            'by_mode' => $counts,
            'default_mode' => (string) config('najm-hoda-founder-action-policy.default_mode', 'forbidden'),
            'delegation_globally_enabled' => (bool) config('najm-hoda-founder-action-policy.delegation.enabled', false),
            'active_delegations_count' => count($activeDelegations),
            'active_delegations' => $activeDelegations,
            'fail_closed' => (string) config('najm-hoda-founder-action-policy.default_mode', 'forbidden') === 'forbidden',
        ];
    }
}
