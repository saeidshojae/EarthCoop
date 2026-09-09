<?php

namespace App\Services\LocationGovernance;

use App\Exceptions\ResidenceTransferLimitExceeded;
use App\Models\Location;
use App\Models\User;
use App\Models\UserLocationRelationship;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ResidenceService
{
    public function __construct(
        private readonly ResidenceTransferPolicy $transferPolicy,
        private readonly GovernanceResolver $governanceResolver,
    ) {
    }

    public function setInitialPrimaryResidence(User $user, Location $location, array $evidence): UserLocationRelationship
    {
        return DB::transaction(function () use ($user, $location, $evidence): UserLocationRelationship {
            $existing = UserLocationRelationship::query()
                ->where('user_id', $user->id)
                ->where('relationship_type', 'primary_residence')
                ->whereNull('ended_at')
                ->lockForUpdate()
                ->first();

            if ($existing !== null) {
                return $existing;
            }

            return UserLocationRelationship::query()->create([
                'user_id' => $user->id,
                'location_id' => $location->id,
                'relationship_type' => 'primary_residence',
                'started_at' => now(),
                'ended_at' => null,
                'evidence' => $evidence,
                'explicit_transfer' => false,
                'transfer_override' => false,
            ]);
        });
    }

    public function transferPrimaryResidence(
        User $user,
        Location $to,
        User $actor,
        string $reason,
        bool $override = false,
    ): UserLocationRelationship {
        $at = now();

        if (! $override && ! $this->transferPolicy->allowsExplicitTransfer($user, $at)) {
            throw new ResidenceTransferLimitExceeded('The rolling primary-residence transfer limit has been reached.');
        }

        return DB::transaction(function () use ($user, $to, $actor, $reason, $override, $at): UserLocationRelationship {
            $current = UserLocationRelationship::query()
                ->where('user_id', $user->id)
                ->where('relationship_type', 'primary_residence')
                ->whereNull('ended_at')
                ->lockForUpdate()
                ->first();

            if ($current !== null) {
                $current->forceFill(['ended_at' => $at])->save();
            }

            return UserLocationRelationship::query()->create([
                'user_id' => $user->id,
                'location_id' => $to->id,
                'relationship_type' => 'primary_residence',
                'started_at' => $at,
                'ended_at' => null,
                'evidence' => [],
                'explicit_transfer' => true,
                'transfer_override' => $override,
                'changed_by_user_id' => $actor->id,
                'change_reason' => $reason,
            ]);
        });
    }

    public function officialGovernanceAreasFor(User $user): Collection
    {
        $primaryResidence = UserLocationRelationship::query()
            ->where('user_id', $user->id)
            ->where('relationship_type', 'primary_residence')
            ->whereNull('ended_at')
            ->with('location')
            ->orderByDesc('started_at')
            ->orderByDesc('id')
            ->first();

        if ($primaryResidence === null || $primaryResidence->location === null) {
            return collect();
        }

        return $this->governanceResolver->officialAreasForResidence($primaryResidence->location);
    }
}
