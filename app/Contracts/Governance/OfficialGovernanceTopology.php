<?php

namespace App\Contracts\Governance;

use App\Models\GovernanceArea;
use Illuminate\Support\Collection;

interface OfficialGovernanceTopology
{
    public function parentOf(GovernanceArea $area): ?GovernanceArea;

    public function childrenOf(GovernanceArea $area): Collection;
}
