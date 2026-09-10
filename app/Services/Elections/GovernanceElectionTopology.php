<?php

namespace App\Services\Elections;

use App\Contracts\Governance\OfficialGovernanceTopology;
use App\Models\GovernanceArea;
use Illuminate\Support\Collection;

class GovernanceElectionTopology implements OfficialGovernanceTopology
{
    public function parentOf(GovernanceArea $area): ?GovernanceArea
    {
        if (! $this->isOfficialActive($area) || $area->parent_id === null) {
            return null;
        }

        return GovernanceArea::query()
            ->whereKey($area->parent_id)
            ->where('area_kind', 'official')
            ->where('status', 'active')
            ->first();
    }

    public function childrenOf(GovernanceArea $area): Collection
    {
        if (! $this->isOfficialActive($area)) {
            return collect();
        }

        return GovernanceArea::query()
            ->where('parent_id', $area->id)
            ->where('area_kind', 'official')
            ->where('status', 'active')
            ->orderByDesc('rank')
            ->orderBy('id')
            ->get();
    }

    private function isOfficialActive(GovernanceArea $area): bool
    {
        return $area->area_kind === 'official' && $area->status === 'active';
    }
}
