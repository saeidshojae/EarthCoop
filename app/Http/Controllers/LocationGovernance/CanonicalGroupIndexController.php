<?php

namespace App\Http\Controllers\LocationGovernance;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Group\GroupController;
use App\Models\Group;
use App\Services\Groups\CanonicalGroupMembershipReconciler;
use App\Services\Groups\PendingLocationGroupRequestService;
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

        // Healing a pre-deploy ready shell can create its missing official
        // GovernanceArea and canonical groups. Reconcile once more afterwards
        // so this same response includes those newly materialized groups.
        app(PendingLocationGroupRequestService::class)->reconcileReadyForUser($user);
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

        $canonicalGroups->each(fn (Group $group) => $group->setAttribute(
            'presentation_rank',
            $this->presentationDepthFor((string) ($group->governanceArea?->governance_type ?? '')),
        ));

        $pendingService = app(PendingLocationGroupRequestService::class);
        $pendingRequests = $pendingService->openForUser($user);
        $canonicalGroups = $pendingService->presentableCanonicalGroups($canonicalGroups, $pendingRequests);
        $pendingGroups = $pendingService->presentationGroups($pendingRequests);
        $allGroups = $canonicalGroups
            ->concat($pendingGroups)
            ->sortByDesc(fn (Group $group): int => (int) ($group->presentation_rank ?? -1))
            ->values();

        return view('groups.index', [
            'generalGroups' => $this->dimension($allGroups, 'public'),
            'specialityGroups' => $this->dimension($allGroups, 'profession'),
            'experienceGroups' => $this->dimension($allGroups, 'specialty'),
            'ageGroups' => $this->dimension($allGroups, 'age'),
            'genderGroups' => $this->dimension($allGroups, 'gender'),
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

    private function presentationDepthFor(string $governanceType): int
    {
        return match ($governanceType) {
            'global' => 1,
            'continent' => 2,
            'country' => 3,
            'province' => 4,
            'county' => 5,
            'section' => 6,
            'city', 'rural_district' => 7,
            'urban_region', 'village' => 8,
            'local', 'neighborhood' => 9,
            default => 0,
        };
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
