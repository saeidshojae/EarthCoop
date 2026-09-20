<?php

namespace App\Http\Controllers\LocationGovernance;

use App\Http\Controllers\Controller;
use App\Models\GovernanceArea;
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
            ])
            ->latest('selected_at')
            ->first();

        $governanceAreas = $residenceService->officialGovernanceAreasFor($user);

        $canonicalMemberships = $user->groups()
            ->whereNotNull('governance_area_id')
            ->wherePivot('status', 1)
            ->with('governanceArea')
            ->get();

        $membershipsByDimension = collect(self::DIMENSIONS)
            ->mapWithKeys(function (string $dimension) use ($canonicalMemberships): array {
                $memberships = $canonicalMemberships
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

        $communityOptions = $localCommunityLocations->map(function ($location) use ($communitiesByLocation, $communityCreationPolicy, $pendingResidenceIntent, $user): array {
            $community = $communitiesByLocation->get($location->id);

            return [
                'location' => $location,
                'community' => $community,
                'group' => $community !== null ? $communityAreaService->ensureMembership($community, $user) : null,
                'can_create' => $community === null
                    && $pendingResidenceIntent === null
                    && $communityCreationPolicy->mayCreateFor($location, $user),
            ];
        });

        return view('location-governance.my-location-governance', compact(
            'currentResidence',
            'pendingResidenceIntent',
            'governanceAreas',
            'membershipsByDimension',
            'communities',
            'communityOptions',
        ));
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
