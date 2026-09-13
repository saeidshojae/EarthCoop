<?php

namespace App\Http\Controllers\LocationGovernance;

use App\Http\Controllers\Controller;
use App\Models\GovernanceArea;
use App\Services\LocationGovernance\CommunityCreationPolicy;
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

        $communities = $this->communitiesForResidence($currentResidence?->location_id);
        $canCreateCommunity = $pendingResidenceIntent === null
            && $communities->isEmpty()
            && $currentResidence?->location !== null
            && $communityCreationPolicy->mayCreateFor($currentResidence->location, $user);

        return view('location-governance.my-location-governance', compact(
            'currentResidence',
            'pendingResidenceIntent',
            'governanceAreas',
            'membershipsByDimension',
            'communities',
            'canCreateCommunity',
        ));
    }

    private function communitiesForResidence(?int $locationId): Collection
    {
        if ($locationId === null) {
            return collect();
        }

        return GovernanceArea::query()
            ->where('area_kind', 'community')
            ->where('status', 'active')
            ->whereHas('locations', fn ($query) => $query->whereKey($locationId))
            ->orderBy('rank')
            ->orderBy('id')
            ->get();
    }
}
