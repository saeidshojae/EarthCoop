<?php

namespace App\Services\Group\Api;

use App\Models\Group;
use App\Models\User;
use App\Services\Groups\CanonicalGroupMembershipReconciler;
use App\Services\Groups\PendingLocationGroupRequestService;
use Illuminate\Support\Collection;

final class CanonicalGroupQueryService
{
    private const ROLE_LABELS = [
        0 => 'ناظر',
        1 => 'فعال',
        2 => 'بازرس',
        3 => 'مدیر',
        4 => 'مهمان',
        5 => 'فعال موقت',
    ];

    public function __construct(
        private readonly CanonicalGroupMembershipReconciler $reconciler,
        private readonly PendingLocationGroupRequestService $pendingGroups,
    ) {
    }

    public function listFor(User $user, ?string $search = null): Collection
    {
        $groupsEnabled = (bool) config('location-governance.groups_enabled', false);
        if ($groupsEnabled) {
            $this->reconciler->reconcile($user);
        }

        $query = $user->groups()
            ->wherePivot('status', 1)
            ->whereNotNull('groups.governance_area_id')
            ->whereNotNull('groups.dimension_key')
            ->whereNotNull('groups.dimension_value_key')
            ->with('governanceArea')
            ->orderBy('groups.id');

        $search = trim((string) $search);
        if ($search !== '') {
            $query->where('groups.name', 'like', '%'.$search.'%');
        }

        $groups = $query->get();

        if ($groupsEnabled) {
            $pendingRequests = $this->pendingGroups->openForUser($user);
            $groups = $this->pendingGroups->presentableCanonicalGroups($groups, $pendingRequests);
            $pending = $this->pendingGroups->presentationGroups($pendingRequests);
            if ($search !== '') {
                $pending = $pending->filter(
                    fn (Group $group): bool => mb_stripos((string) $group->name, $search) !== false
                )->values();
            }
            $groups = $groups->concat($pending)->values();
        }

        return $groups->map(fn (Group $group): array => $this->serialize($group))->values();
    }

    public function findFor(User $user, Group $group): array
    {
        if ((bool) config('location-governance.groups_enabled', false)) {
            $this->reconciler->reconcile($user);
        }

        $memberGroup = $user->groups()
            ->wherePivot('status', 1)
            ->where('groups.id', $group->id)
            ->whereNotNull('groups.governance_area_id')
            ->whereNotNull('groups.dimension_key')
            ->whereNotNull('groups.dimension_value_key')
            ->with('governanceArea')
            ->firstOrFail();

        return $this->serialize($memberGroup);
    }

    private function serialize(Group $group): array
    {
        $role = (int) $group->pivot->role;
        $pending = (bool) $group->getAttribute('pending_location');

        return [
            'id' => $pending ? null : (int) $group->id,
            'name' => (string) $group->name,
            'identity' => [
                'governance_area_id' => $pending ? null : (int) $group->governance_area_id,
                'dimension_key' => (string) $group->dimension_key,
                'dimension_value_key' => (string) $group->dimension_value_key,
            ],
            'membership' => [
                'role' => $role,
                'role_label' => self::ROLE_LABELS[$role] ?? 'نامشخص',
                'status' => (int) $group->pivot->status,
            ],
            'members_count' => $pending ? 0 : $group->userCount(),
            'last_activity_at' => $pending ? null : $group->last_activity_at?->utc()?->toIso8601ZuluString(),
            'pending' => $pending,
            'pending_request_id' => $pending
                ? (int) $group->getAttribute('pending_location_request_id')
                : null,
            'can_open' => ! $pending,
        ];
    }
}
