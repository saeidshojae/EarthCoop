<?php

namespace App\Services\Groups;

use App\Enums\LocationGovernance\LocationProposalStatus;
use App\Models\GroupUser;
use App\Models\LocationStructureClaim;
use App\Models\PendingResidenceIntent;
use App\Models\User;
use App\Services\GroupService;
use App\Services\LocationGovernance\GovernanceResolver;

final class CanonicalGroupMembershipReconciler
{
    private const SYSTEM_DIMENSIONS = [
        'public',
        'profession',
        'specialty',
        'age',
        'gender',
    ];

    public function __construct(
        private readonly GroupService $groupService,
        private readonly GovernanceResolver $governanceResolver,
    ) {
    }

    /**
     * Reconcile the user's active canonical system-group memberships against
     * the current Primary Residence + membership-dimension resolution.
     *
     * The base official governance scope is the user's normal active membership
     * (role=1). Official upstream scopes are observers (role=0). Existing active
     * privileged roles (manager/inspector/etc.) are preserved; a stale membership
     * that is reactivated returns to its normal base/upstream role.
     *
     * Legacy memberships are intentionally left untouched so Stage C remains
     * reversible. Canonical memberships that no longer belong to the current
     * resolution are retained as history but marked inactive.
     *
     * @return array<int, \App\Models\Group>
     */
    public function reconcile(User $user): array
    {
        if (! (bool) config('location-governance.groups_enabled', false)) {
            return [];
        }

        $groups = collect($this->groupService->getGroupsForUser($user))
            ->unique('id')
            ->values();

        $activeGroupIds = $groups
            ->pluck('id')
            ->filter()
            ->map(fn ($id): int => (int) $id)
            ->values()
            ->all();

        $baseAreaId = $this->baseGovernanceAreaId($user);

        foreach ($groups as $group) {
            $membership = GroupUser::query()
                ->where('user_id', $user->id)
                ->where('group_id', $group->id)
                ->first();

            if ($membership === null) {
                continue;
            }

            $desiredRole = $baseAreaId !== null
                && (int) $group->governance_area_id === $baseAreaId
                ? 1
                : 0;

            $updates = ['status' => 1];
            $currentRole = (int) $membership->role;
            $wasActive = (int) $membership->status === 1;

            if (! $wasActive || in_array($currentRole, [0, 1], true)) {
                $updates['role'] = $desiredRole;
            }

            $membership->forceFill($updates)->save();
        }

        $staleMemberships = GroupUser::query()
            ->where('user_id', $user->id)
            ->where('status', 1)
            ->whereHas('group', function ($query): void {
                $query
                    ->whereNotNull('governance_area_id')
                    ->whereIn('dimension_key', self::SYSTEM_DIMENSIONS)
                    ->whereNotNull('dimension_value_key')
                    ->whereHas('governanceArea', fn ($area) => $area->where('area_kind', 'official'));
            });

        if ($activeGroupIds !== []) {
            $staleMemberships->whereNotIn('group_id', $activeGroupIds);
        }

        $staleMemberships->update(['status' => 0]);

        return $groups->all();
    }

    private function baseGovernanceAreaId(User $user): ?int
    {
        // An open official residence refinement is already the user's chosen
        // base, even though it cannot materialize yet. Never promote the
        // nearest approved ancestor to active merely because the chosen base
        // is awaiting review.
        $pendingOfficialBase = PendingResidenceIntent::query()
            ->where('user_id', $user->id)
            ->where('status', 'pending')
            ->with(['locationProposal.type', 'referenceSettlementResidenceClaim.settlement'])
            ->latest('id')
            ->first();

        if ($pendingOfficialBase?->locationProposal !== null) {
            $proposal = $pendingOfficialBase->locationProposal;
            $status = $proposal->status instanceof LocationProposalStatus
                ? $proposal->status
                : LocationProposalStatus::tryFrom((string) $proposal->status);

            if ($status !== null
                && in_array($status, [
                    LocationProposalStatus::Pending,
                    LocationProposalStatus::ReadyForReview,
                    LocationProposalStatus::NeedsEvidence,
                ], true)
                && in_array($proposal->type?->key, ['city', 'rural_district', 'urban_region', 'village', 'neighborhood'], true)
            ) {
                return null;
            }
        }

        if ($pendingOfficialBase?->referenceSettlementResidenceClaim !== null) {
            $settlementClaim = $pendingOfficialBase->referenceSettlementResidenceClaim;
            $settlement = $settlementClaim->settlement;

            if ($settlement !== null
                && in_array($settlementClaim->status, ['pending', 'needs_evidence', 'residential_evidence_verified'], true)
                && in_array($settlement->classification, ['unverified_settlement', 'needs_review', 'verified_residential_village'], true)
            ) {
                // The exact chosen base is still a settlement claim. Keep all
                // canonical ancestors observer-only; the pending shell represents
                // the user's chosen base until a separate governance materialization.
                return null;
            }
        }

        $currentResidence = $user->locationRelationships()
            ->where('relationship_type', 'primary_residence')
            ->whereNull('ended_at')
            ->with('location')
            ->latest('started_at')
            ->latest('id')
            ->first();

        $location = $currentResidence?->location;
        if ($location === null) {
            return null;
        }

        $structuralClaimIds = collect(($currentResidence->metadata ?? [])['structural_claim_ids'] ?? [])
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->unique()
            ->values();

        if ($structuralClaimIds->isNotEmpty()) {
            $pendingBaseClaim = LocationStructureClaim::query()
                ->whereIn('id', $structuralClaimIds)
                ->where('location_id', $location->id)
                ->where('claim_type', 'no_neighborhood')
                ->where('status', '<>', 'approved')
                ->exists();

            if ($pendingBaseClaim) {
                return null;
            }
        }

        $area = $this->governanceResolver->baseOfficialAreaForResidence($location);

        return $area === null ? null : (int) $area->id;
    }
}
