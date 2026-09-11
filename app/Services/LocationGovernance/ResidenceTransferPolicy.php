<?php

namespace App\Services\LocationGovernance;

use App\Models\User;
use App\Models\UserLocationRelationship;
use Carbon\CarbonInterface;

class ResidenceTransferPolicy
{
    public const DEFAULT_MAX_EXPLICIT_TRANSFERS = 2;

    public function remainingExplicitTransfers(User $user, CarbonInterface $at): int
    {
        $windowStart = $at->copy()->subYear();

        $used = UserLocationRelationship::query()
            ->where('user_id', $user->id)
            ->where('relationship_type', 'primary_residence')
            ->where('explicit_transfer', true)
            ->where('started_at', '>', $windowStart)
            ->where('started_at', '<=', $at)
            ->count();

        return max(0, self::DEFAULT_MAX_EXPLICIT_TRANSFERS - $used);
    }

    public function allowsExplicitTransfer(User $user, CarbonInterface $at): bool
    {
        return $this->remainingExplicitTransfers($user, $at) > 0;
    }
}
