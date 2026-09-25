<?php

namespace App\Http\Controllers\LocationGovernance;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Profile\ProfileController;
use App\Models\Address;
use App\Models\ExperienceField;
use App\Models\Location;
use App\Models\LocationExternalId;
use App\Models\LocationProposal;
use App\Services\LocationGovernance\IranV2RuntimeState;
use App\Models\OccupationalField;
use App\Models\PendingResidenceIntent;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class ProfileEditController extends Controller
{
    public function __invoke(Request $request): View
    {
        if (! (bool) config('location-governance.registration_enabled')) {
            return app(ProfileController::class)->editModifiable();
        }

        $user = $request->user();
        abort_unless($user !== null, 401);

        $primaryResidence = $user->locationRelationships()
            ->with('location')
            ->where('relationship_type', 'primary_residence')
            ->whereNull('ended_at')
            ->latest('started_at')
            ->latest('id')
            ->first();

        $pendingResidenceIntent = $user->pendingResidenceIntents()
            ->with(['locationProposal.type', 'referenceSettlementResidenceClaim.settlement', 'resolvedLocation'])
            ->where('status', 'pending')
            ->latest('selected_at')
            ->latest('id')
            ->first();

        $residenceHydrationPath = $this->residenceHydrationPath(
            $primaryResidence?->location,
            $pendingResidenceIntent,
        );
        $referenceSettlementProposalPath = $this->referenceSettlementProposalPath($pendingResidenceIntent);

        $occupationalFields = OccupationalField::whereNull('parent_id')->get();
        $experienceFields = ExperienceField::whereNull('parent_id')->get();
        $allOccupationalFields = OccupationalField::with('parent')->get();
        $allExperienceFields = ExperienceField::with('parent')->get();
        $level1Fields = OccupationalField::whereNull('parent_id')->get();
        $level1ExperienceFields = ExperienceField::whereNull('parent_id')->get();

        // The canonical selector owns residence state. The surrounding legacy
        // profile template still contains presentation-only reads such as
        // $user->address->city_id in old JavaScript. Supply an unsaved, empty
        // relation so those reads remain harmless without creating or mutating
        // any legacy Address row. Flag-off requests still use the legacy
        // controller unchanged above.
        if ($user->address === null) {
            $user->setRelation('address', new Address());
        }

        // The canonical location partial no longer consumes the legacy fixed-depth
        // geography collections. Keep the variables present so the surrounding
        // profile view remains backward-compatible while the flag is enabled.
        $empty = collect();

        return view('profile.edit', [
            'user' => $user,
            'primaryResidence' => $primaryResidence,
            'pendingResidenceIntent' => $pendingResidenceIntent,
            'residenceHydrationPath' => $residenceHydrationPath,
            'referenceSettlementProposalPath' => $referenceSettlementProposalPath,
            'occupationalFields' => $occupationalFields,
            'experienceFields' => $experienceFields,
            'allOccupationalFields' => $allOccupationalFields,
            'allExperienceFields' => $allExperienceFields,
            'level1Fields' => $level1Fields,
            'level1ExperienceFields' => $level1ExperienceFields,
            'continents' => $empty,
            'countries' => $empty,
            'provinces' => $empty,
            'counties' => $empty,
            'sections' => $empty,
            'cities' => $empty,
            'regions' => $empty,
            'neighborhoods' => $empty,
            'streets' => $empty,
            'alleys' => $empty,
            'countryCodes' => [],
        ]);
    }

    /** @return array<int, string> */
    private function residenceHydrationPath(
        ?Location $primaryResidenceLocation,
        ?PendingResidenceIntent $intent,
    ): array {
        $referenceAnchor = $this->referenceSettlementAnchor($intent);
        if ($referenceAnchor instanceof Location) {
            return $this->canonicalLocationPath($referenceAnchor);
        }

        $proposal = $intent?->locationProposal;
        if (! $proposal instanceof LocationProposal) {
            return $this->canonicalLocationPath($primaryResidenceLocation);
        }

        $proposalPath = [];
        $proposalCursor = $proposal;
        $visitedProposalIds = [];
        $canonicalAnchor = null;

        while ($proposalCursor !== null) {
            if (isset($visitedProposalIds[$proposalCursor->id])) {
                return $this->canonicalLocationPath($primaryResidenceLocation);
            }
            $visitedProposalIds[$proposalCursor->id] = true;
            array_unshift($proposalPath, 'proposal:'.$proposalCursor->id);

            if ($proposalCursor->parent_location_id !== null) {
                $canonicalAnchor = $proposalCursor->parentLocation()->first();
                break;
            }

            $proposalCursor = $proposalCursor->parentProposal()->first();
        }

        if (! $canonicalAnchor instanceof Location) {
            return $this->canonicalLocationPath($primaryResidenceLocation);
        }

        return [...$this->canonicalLocationPath($canonicalAnchor), ...$proposalPath];
    }

    private function referenceSettlementAnchor(?PendingResidenceIntent $intent): ?Location
    {
        $settlement = $intent?->referenceSettlementResidenceClaim?->settlement;
        $parentExternalId = $settlement?->parent_external_id;
        if (! is_string($parentExternalId) || $parentExternalId === '') {
            return null;
        }

        return LocationExternalId::query()
            ->with('location')
            ->where('source', 'earthcoop-reference')
            ->where('dataset_version', 'v2')
            ->where('external_id', $parentExternalId)
            ->first()?->location;
    }

    /** @return array<int, int> */
    private function referenceSettlementProposalPath(?PendingResidenceIntent $intent): array
    {
        if ($intent?->reference_settlement_residence_claim_id === null || ! $intent?->locationProposal instanceof LocationProposal) {
            return [];
        }

        $path = [];
        $cursor = $intent->locationProposal;
        $visited = [];
        while ($cursor !== null) {
            if (isset($visited[$cursor->id])) {
                return [];
            }
            $visited[$cursor->id] = true;
            array_unshift($path, (int) $cursor->id);
            if ($cursor->parent_reference_settlement_id !== null) {
                return $path;
            }
            $cursor = $cursor->parentProposal()->first();
        }

        return [];
    }

    /** @return array<int, string> */
    private function canonicalLocationPath(?Location $location): array
    {
        if (! $location instanceof Location) {
            return [];
        }

        $location = $this->preferredHydrationLocation($location);

        $path = [];
        $cursor = $location;
        $visitedLocationIds = [];
        $root = null;

        while ($cursor !== null) {
            if (isset($visitedLocationIds[$cursor->id])) {
                return [];
            }
            $visitedLocationIds[$cursor->id] = true;
            $root = $cursor;
            array_unshift($path, 'location:'.$cursor->id);
            $cursor = $cursor->parent()->first();
        }

        if ($root instanceof Location) {
            $countryArea = $root->governanceAreas()->official()->active()->where('governance_type', 'country')->first();
            $continentArea = $countryArea?->parent()->official()->active()->where('governance_type', 'continent')->first();
            if ($continentArea !== null) {
                array_unshift($path, 'governance:'.$continentArea->id);
            }
        }

        return $path;
    }
    private function preferredHydrationLocation(Location $location): Location
    {
        if ($location->country_code !== 'IR' || ! app(IranV2RuntimeState::class)->isActive()) {
            return $location;
        }

        $alreadyV2 = LocationExternalId::query()
            ->where('location_id', $location->id)
            ->where('source', 'earthcoop-reference')
            ->where('dataset_version', 'v2')
            ->exists();
        if ($alreadyV2) {
            return $location;
        }

        $v1ExternalId = LocationExternalId::query()
            ->where('location_id', $location->id)
            ->where('source', config('iran_v1_v2_crosswalk.source', 'earthcoop-reference'))
            ->where('dataset_version', config('iran_v1_v2_crosswalk.v1_dataset_version', 'v1'))
            ->value('external_id');
        if (! is_string($v1ExternalId) || $v1ExternalId === '') {
            return $location;
        }

        $mapping = config('iran_v1_v2_crosswalk.mappings.'.$v1ExternalId);
        if (! is_array($mapping) || ($mapping['status'] ?? null) !== 'verified_identity') {
            return $location;
        }

        $v2 = LocationExternalId::query()
            ->with('location')
            ->where('source', config('iran_v1_v2_crosswalk.source', 'earthcoop-reference'))
            ->where('dataset_version', config('iran_v1_v2_crosswalk.v2_dataset_version', 'v2'))
            ->where('external_id', (string) ($mapping['v2'] ?? ''))
            ->first()?->location;

        return $v2 instanceof Location && $v2->status === 'active' ? $v2 : $location;
    }

}
