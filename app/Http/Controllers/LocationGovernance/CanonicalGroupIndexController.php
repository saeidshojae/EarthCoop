<?php

namespace App\Http\Controllers\LocationGovernance;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Group\GroupController;
use App\Models\Group;
use App\Services\GroupService;
use Illuminate\Support\Collection;
use Illuminate\View\View;

final class CanonicalGroupIndexController extends Controller
{
    public function __invoke(GroupService $groupService): View
    {
        if (! (bool) config('location-governance.groups_enabled', false)) {
            return app(GroupController::class)->index();
        }

        $user = auth()->user();
        abort_unless($user !== null, 401);

        $materializedIds = collect($groupService->getGroupsForUser($user))
            ->pluck('id')
            ->filter()
            ->values();

        $canonicalGroups = $materializedIds->isEmpty()
            ? collect()
            : $user->groups()
                ->withPivot('role', 'status', 'expired', 'last_read_message_id')
                ->whereIn('groups.id', $materializedIds->all())
                ->wherePivot('status', 1)
                ->get()
                ->reverse()
                ->values();

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
}
