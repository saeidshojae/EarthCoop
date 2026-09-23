<?php

namespace App\Http\Controllers\LocationGovernance;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Profile\ProfileController;
use App\Models\Address;
use App\Models\ExperienceField;
use App\Models\Location;
use App\Models\LocationProposal;
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

    /** @return array<int, string> */
    private function canonicalLocationPath(?Location $location): array
    {
        if (! $location instanceof Location) {
            return [];
        }

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
}
