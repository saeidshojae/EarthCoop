<?php

namespace App\Services\Groups;

use App\Enums\LocationGovernance\LocationProposalStatus;
use App\Models\LocationProposal;
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
}
