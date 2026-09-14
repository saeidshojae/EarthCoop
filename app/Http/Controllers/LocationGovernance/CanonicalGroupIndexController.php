<?php

namespace App\Http\Controllers\LocationGovernance;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Group\GroupController;
use App\Models\Group;
use App\Services\Groups\CanonicalGroupMembershipReconciler;
use Illuminate\Support\Collection;
use Illuminate\View\View;

final class CanonicalGroupIndexController extends Controller
{
    public function __invoke(CanonicalGroupMembershipReconciler $reconciler): View
    {
        if (! (bool) config('location-governance.groups_enabled', false)) {
            return app(GroupController::class)->index();
        }

        $user = auth()->user();
        abort_unless($user !== null, 401);

        $materializedIds = collect($reconciler->reconcile($user))
            ->pluck('id')
            ->filter()
            ->values();

        $canonicalGroups = $materializedIds->isEmpty()
            ? collect()
            : $user->groups()
                ->with(['governanceArea'])
                ->withPivot('role', 'status', 'expired', 'last_read_message_id')
                ->whereIn('groups.id', $materializedIds->all())
                ->wherePivot('status', 1)
                ->get()
                ->sortByDesc(fn (Group $group): int => (int) ($group->governanceArea?->rank ?? -1))
                ->values();

        // The mature My Groups view still filters profession/specialty rows by the
        // legacy `location_level` presentation field. Canonical group identity must
        // remain governance_area_id-based, so adapt the value in-memory only for
        // this response; never persist it back to the legacy column.
        $canonicalGroups->each(function (Group $group): void {
            if ($group->location_level !== null || $group->governanceArea === null) {
                return;
            }

            $group->setAttribute(
                'location_level',
                $this->presentationLevelFor((string) $group->governanceArea->governance_type),
            );
        });

        return view('groups.index', [
            'generalGroups' => $this->dimension($canonicalGroups, 'public'),
            'specialityGroups' => $this->dimension($canonicalGroups, 'profession'),
            'experienceGroups' => $this->dimension($canonicalGroups, 'specialty'),
            'ageGroups' => $this->dimension($canonicalGroups, 'age'),
            'genderGroups' => $this->dimension($canonicalGroups, 'gender'),
            // Exclusive/user-managed groups are not canonical spatial memberships.
            // Preserve their mature legacy path unchanged during Stage C.
            'managedGroups' => $user->groups()
                ->withPivot('role', 'status', 'expired', 'last_read_message_id')
                ->where('location_level', 10)
                ->get(),
        ]);
    }

    /** @param Collection<int, Group> $groups */
    private function dimension(Collection $groups, string $dimensionKey): Collection
    {
        return $groups
            ->filter(fn (Group $group): bool => $group->dimension_key === $dimensionKey)
            ->values();
    }

    private function presentationLevelFor(string $governanceType): ?string
    {
        return match ($governanceType) {
            'global' => 'global',
            'continent' => 'continent',
            'country' => 'country',
            'province' => 'province',
            'county' => 'county',
            'section' => 'section',
            'city', 'rural_district' => 'city',
            'urban_region', 'village' => 'region',
            'local', 'neighborhood' => 'neighborhood',
            default => null,
        };
    }
}
