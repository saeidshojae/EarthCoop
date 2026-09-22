<?php

namespace App\Services\Groups;

use App\Enums\LocationGovernance\LocationProposalStatus;
use App\Models\Location;
use App\Models\LocationProposal;
use App\Models\LocationStructureClaim;
use App\Models\Group;
use App\Models\GroupUser;
use App\Models\LocationScopedGroupRequest;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final class PendingLocationGroupRequestService
{
    private const OFFICIAL_TYPES = ['city', 'rural_district', 'urban_region', 'village', 'neighborhood'];
    private const OPEN_STATUSES = [LocationProposalStatus::Pending, LocationProposalStatus::ReadyForReview, LocationProposalStatus::NeedsEvidence];

    /** @return Collection<int, LocationScopedGroupRequest> */
    public function syncForPendingResidence(User $user, LocationProposal $deepest): Collection
    {
        return DB::transaction(function () use ($user, $deepest): Collection {
            $chain = collect();
            $cursor = $deepest->loadMissing('type');
            $visited = [];
            while ($cursor !== null && ! isset($visited[$cursor->id])) {
                $visited[$cursor->id] = true;
                $status = $cursor->status instanceof LocationProposalStatus ? $cursor->status : LocationProposalStatus::tryFrom((string) $cursor->status);
                if ($status !== null && in_array($status, self::OPEN_STATUSES, true) && in_array($cursor->type?->key, self::OFFICIAL_TYPES, true)) $chain->push($cursor);
                $cursor = $cursor->parentProposal()->with('type')->first();
            }

            $activeProposalIds = $chain->pluck('id')->map(fn ($id) => (int) $id)->all();
            $stale = LocationScopedGroupRequest::query()->where('requester_user_id', $user->id)->where('scope_kind', 'official_public')->whereIn('status', ['pending_location', 'ready_to_materialize']);
            if ($activeProposalIds !== []) $stale->whereNotIn('location_proposal_id', $activeProposalIds);
            $stale->update(['status' => 'cancelled']);

            return $chain->map(fn (LocationProposal $proposal) => LocationScopedGroupRequest::query()->updateOrCreate(
                ['requester_user_id' => $user->id, 'location_proposal_id' => $proposal->id, 'scope_kind' => 'official_public'],
                ['location_id' => null, 'location_structure_claim_id' => null, 'status' => 'pending_location', 'metadata' => ['type_key' => $proposal->type?->key, 'canonical_name' => $proposal->canonical_name, 'source' => 'pending_primary_residence']]
            ))->values();
        });
    }
    /** @return Collection<int, LocationScopedGroupRequest> */
    public function syncForStructuralClaims(User $user, Location $location, array $claims): Collection
    {
        return collect($claims)
            ->filter(fn ($claim): bool => $claim instanceof LocationStructureClaim
                && (int) $claim->location_id === (int) $location->id
                && in_array($claim->status, ['pending', 'ready_for_review', 'needs_evidence'], true)
                && $claim->claim_type === 'no_neighborhood')
            ->map(fn (LocationStructureClaim $claim) => LocationScopedGroupRequest::query()->firstOrCreate(
                [
                    'requester_user_id' => $user->id,
                    'location_structure_claim_id' => $claim->id,
                    'scope_kind' => 'official_public',
                ],
                [
                    'location_id' => null,
                    'location_proposal_id' => null,
                    'status' => 'pending_location',
                    'metadata' => [
                        'claim_type' => $claim->claim_type,
                        'source' => 'pending_primary_residence_structure',
                    ],
                ]
            ))->values();
    }

    public function reconcileResolvedProposal(LocationProposal $proposal, Location $location): void
    {
        $requests = LocationScopedGroupRequest::query()
            ->where('location_proposal_id', $proposal->id)
            ->whereIn('status', ['pending_location', 'ready_to_materialize'])
            ->lockForUpdate()
            ->get();

        foreach ($requests as $request) {
            $metadata = $request->metadata ?? [];
            $metadata['resolved_from_proposal_id'] = $proposal->id;
            $request->forceFill([
                'location_id' => $location->id,
                'location_proposal_id' => null,
                'status' => 'ready_to_materialize',
                'metadata' => $metadata,
            ])->save();

            $this->tryMaterializeOfficialRequest($request);
        }
    }

    public function rejectForProposal(LocationProposal $proposal): void
    {
        LocationScopedGroupRequest::query()
            ->where('location_proposal_id', $proposal->id)
            ->whereIn('status', ['pending_location', 'ready_to_materialize'])
            ->update(['status' => 'rejected', 'updated_at' => now()]);
    }

    public function reconcileStructuralClaim(LocationStructureClaim $claim): void
    {
        $requests = LocationScopedGroupRequest::query()
            ->where('location_structure_claim_id', $claim->id)
            ->whereIn('status', ['pending_location', 'ready_to_materialize'])
            ->lockForUpdate()
            ->get();

        foreach ($requests as $request) {
            if ($claim->status === 'rejected') {
                $request->forceFill(['status' => 'rejected'])->save();
                continue;
            }

            if ($claim->status !== 'approved' || $claim->location_id === null) {
                continue;
            }

            $metadata = $request->metadata ?? [];
            $metadata['resolved_from_structure_claim_id'] = $claim->id;
            $request->forceFill([
                'location_id' => $claim->location_id,
                'location_structure_claim_id' => null,
                'status' => 'ready_to_materialize',
                'metadata' => $metadata,
            ])->save();

            $this->tryMaterializeOfficialRequest($request);
        }
    }

    public function reconcileReadyForUser(User $user): void
    {
        LocationScopedGroupRequest::query()
            ->where('requester_user_id', $user->id)
            ->where('status', 'ready_to_materialize')
            ->get()
            ->each(fn (LocationScopedGroupRequest $request) => $this->tryMaterializeOfficialRequest($request));
    }

    private function tryMaterializeOfficialRequest(LocationScopedGroupRequest $request): void
    {
        if ($request->scope_kind !== 'official_public' || $request->location_id === null) {
            return;
        }

        $area = Location::query()->find($request->location_id)?->governanceAreas()
            ->official()
            ->active()
            ->orderByDesc('rank')
            ->orderBy('governance_areas.id')
            ->first();

        if ($area === null) {
            return;
        }

        app(CanonicalGroupMembershipReconciler::class)->reconcile(
            User::query()->findOrFail($request->requester_user_id)
        );

        $group = Group::query()
            ->where('governance_area_id', $area->id)
            ->where('dimension_key', 'public')
            ->where('dimension_value_key', 'public')
            ->first();

        if ($group === null || ! GroupUser::query()
            ->where('group_id', $group->id)
            ->where('user_id', $request->requester_user_id)
            ->where('status', 1)
            ->exists()) {
            return;
        }

        $request->forceFill([
            'governance_area_id' => $area->id,
            'group_id' => $group->id,
            'status' => 'materialized',
        ])->save();
    }
}
