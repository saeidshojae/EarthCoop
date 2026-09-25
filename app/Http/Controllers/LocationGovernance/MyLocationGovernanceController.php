<?php

namespace App\Http\Controllers\LocationGovernance;

use App\Http\Controllers\Controller;
use App\Models\GovernanceArea;
use App\Models\Group;
use App\Services\Groups\CanonicalGroupMembershipReconciler;
use App\Services\Groups\PendingLocationGroupRequestService;
use App\Services\LocationGovernance\CommunityAreaService;
use App\Services\LocationGovernance\CommunityCreationPolicy;
use App\Services\LocationGovernance\LocationTreeResolver;
use App\Services\LocationGovernance\ResidenceService;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

final class MyLocationGovernanceController extends Controller
{
    private const DIMENSIONS = [
        'public',
        'profession',
        'specialty',
        'age',
        'gender',
    ];

    public function __invoke(
        Request $request,
        ResidenceService $residenceService,
        CommunityCreationPolicy $communityCreationPolicy,
        CommunityAreaService $communityAreaService,
        LocationTreeResolver $locationTreeResolver,
        CanonicalGroupMembershipReconciler $groupMembershipReconciler,
        PendingLocationGroupRequestService $pendingGroupService,
    ): View {
        abort_unless((bool) config('location-governance.runtime_enabled'), 404);

        $user = $request->user();
        $currentResidence = $residenceService->currentPrimaryResidence($user);
        $currentResidence?->loadMissing('location.type');

        $pendingResidenceIntent = $user->pendingResidenceIntents()
            ->where('status', 'pending')
            ->with([
                'locationProposal.parentLocation',
                'locationProposal.type',
                'referenceSettlementResidenceClaim.settlement',
            ])
            ->latest('selected_at')
            ->first();

        // Keep this dashboard derived from the same canonical + pending membership
        // contracts as "My Groups", rather than maintaining a second count model.
        if ((bool) config('location-governance.groups_enabled', false)) {
            $groupMembershipReconciler->reconcile($user);
        }

        $governanceAreas = $residenceService->officialGovernanceAreasFor($user);
        $governanceAreas->each->loadMissing('locations');
        $governanceRankById = $governanceAreas
            ->mapWithKeys(fn ($area): array => [(int) $area->id => $this->governanceDepth((string) $area->governance_type)]);

        $canonicalMemberships = $user->groups()
            ->whereNotNull('governance_area_id')
            ->wherePivot('status', 1)
            ->with('governanceArea')
            ->get()
            ->each(fn (Group $group) => $group->setAttribute(
                'presentation_rank',
                $governanceRankById->get((int) $group->governance_area_id, -1),
            ));

        $pendingRequests = $pendingGroupService->openForUser($user);
        $canonicalMemberships = $pendingGroupService->presentableCanonicalGroups($canonicalMemberships, $pendingRequests);
        $pendingMemberships = $pendingGroupService->presentationGroups($pendingRequests);
        $allMemberships = $canonicalMemberships
            ->concat($pendingMemberships)
            ->sortByDesc(fn (Group $group): int => (int) ($group->presentation_rank ?? -1))
            ->values();

        $pendingGovernanceProposals = $pendingRequests
            ->whereNotNull('location_proposal_id')
            ->unique('location_proposal_id')
            ->map(fn ($request) => $request->locationProposal()->with('type')->first())
            ->filter()
            ->sortByDesc(fn ($proposal): int => $this->governanceDepth((string) $proposal->type?->key))
            ->values();

        $pendingReferenceSettlements = $pendingRequests
            ->whereNotNull('reference_settlement_residence_claim_id')
            ->unique('reference_settlement_residence_claim_id')
            ->map(fn ($request) => $request->referenceSettlementResidenceClaim?->settlement)
            ->filter()
            ->values();

        $pendingGovernanceStructuralClaims = $pendingRequests
            ->whereNotNull('location_structure_claim_id')
            ->unique('location_structure_claim_id')
            ->map(fn ($request) => $request->locationStructureClaim()->with('location.type')->first())
            ->filter(fn ($claim): bool => $claim !== null && $claim->claim_type === 'no_neighborhood')
            ->sortByDesc(fn ($claim): int => $this->governanceDepth((string) $claim->location?->type?->key))
            ->values();

        $governanceLevelCount = $governanceAreas->count()
            + $pendingGovernanceProposals->count()
            + $pendingReferenceSettlements->count()
            + $pendingGovernanceStructuralClaims->count();

        $membershipsByDimension = collect(self::DIMENSIONS)
            ->mapWithKeys(function (string $dimension) use ($allMemberships): array {
                $memberships = $allMemberships
                    ->where('dimension_key', $dimension)
                    ->values();

                return [$dimension => collect([
                    'active' => $memberships
                        ->filter(fn ($group): bool => (int) $group->pivot->role !== 0)
                        ->values(),
                    'observer' => $memberships
                        ->filter(fn ($group): bool => (int) $group->pivot->role === 0)
                        ->values(),
                ])];
            });

        $localCommunityLocations = collect();
        if ($currentResidence?->location !== null) {
            $localCommunityLocations = $locationTreeResolver->ancestors($currentResidence->location)
                ->push($currentResidence->location)
                ->filter(fn ($location): bool => in_array($location->type?->key, ['street', 'alley', 'complex', 'building'], true))
                ->values();
        }

        $communities = $this->communitiesForLocations($localCommunityLocations->pluck('id'));
        $communitiesByLocation = $communities
            ->flatMap(fn ($community) => $community->locations->map(fn ($location) => [$location->id, $community]))
            ->mapWithKeys(fn ($pair) => [$pair[0] => $pair[1]]);

        $communityOptions = $localCommunityLocations->map(function ($location) use ($communitiesByLocation, $communityCreationPolicy, $communityAreaService, $user): array {
            $community = $communitiesByLocation->get($location->id);
            $group = $community !== null ? $communityAreaService->publicAssemblyFor($community) : null;
            $membership = $group !== null
                ? $user->groups()
                    ->where('groups.id', $group->id)
                    ->wherePivot('status', 1)
                    ->first()
                : null;

            return [
                'location' => $location,
                'community' => $community,
                'group' => $group,
                'is_member' => $membership !== null,
                'can_join' => $community !== null
                    && $membership === null
                    && $communityCreationPolicy->mayCreateFor($location, $user),
                'can_create' => $community === null
                    && $communityCreationPolicy->mayCreateFor($location, $user),
            ];
        });

        return view('location-governance.my-location-governance', compact(
            'currentResidence',
            'pendingResidenceIntent',
            'governanceAreas',
            'pendingGovernanceProposals',
            'pendingReferenceSettlements',
            'pendingGovernanceStructuralClaims',
            'governanceLevelCount',
            'membershipsByDimension',
            'communities',
            'communityOptions',
        ));
    }

    private function governanceDepth(string $type): int
    {
        return match ($type) {
            'global' => 1,
            'continent' => 2,
            'country' => 3,
            'province' => 4,
            'county' => 5,
            'section' => 6,
            'city', 'rural_district' => 7,
            'urban_region', 'village', 'settlement' => 8,
            'local', 'neighborhood' => 9,
            default => 0,
        };
    }

    private function communitiesForLocations(Collection $locationIds): Collection
    {
        if ($locationIds->isEmpty()) {
            return collect();
        }

        return GovernanceArea::query()
            ->where('area_kind', 'community')
            ->where('status', 'active')
            ->whereHas('locations', fn ($query) => $query->whereIn('locations.id', $locationIds->all()))
            ->with(['locations' => fn ($query) => $query->whereIn('locations.id', $locationIds->all())->with('type')])
            ->orderBy('rank')
            ->orderBy('id')
            ->get();
    }
}
