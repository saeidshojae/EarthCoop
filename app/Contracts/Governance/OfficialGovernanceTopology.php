<?php

namespace App\Contracts\Governance;

use App\Models\GovernanceArea;
use App\Services\Elections\GovernanceElectionTopology;
use Illuminate\Container\Attributes\Bind;
use Illuminate\Support\Collection;

#[Bind(GovernanceElectionTopology::class)]
interface OfficialGovernanceTopology
{
    public function parentOf(GovernanceArea $area): ?GovernanceArea;

    public function childrenOf(GovernanceArea $area): Collection;
}
