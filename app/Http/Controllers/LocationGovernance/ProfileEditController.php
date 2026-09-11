<?php

namespace App\Http\Controllers\LocationGovernance;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Profile\ProfileController;
use App\Models\Address;
use App\Models\ExperienceField;
use App\Models\OccupationalField;
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
}
