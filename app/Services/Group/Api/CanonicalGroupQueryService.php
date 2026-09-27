<?php

namespace App\Services\Group\Api;

use App\Models\Group;
use App\Models\User;
use App\Services\Groups\CanonicalGroupMembershipReconciler;
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

    public function __construct(private readonly CanonicalGroupMembershipReconciler $reconciler)
    {
    }

    public function listFor(User $user, ?string $search = null): Collection
    {
        if ((bool) config('location-governance.groups_enabled', false)) {
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

        return $query->get()->map(fn (Group $group): array => $this->serialize($group))->values();
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

        return [
            'id' => (int) $group->id,
            'name' => (string) $group->name,
            'identity' => [
                'governance_area_id' => (int) $group->governance_area_id,
                'dimension_key' => (string) $group->dimension_key,
                'dimension_value_key' => (string) $group->dimension_value_key,
            ],
            'membership' => [
                'role' => $role,
                'role_label' => self::ROLE_LABELS[$role] ?? 'نامشخص',
                'status' => (int) $group->pivot->status,
            ],
            'members_count' => $group->userCount(),
            'last_activity_at' => $group->last_activity_at?->utc()?->toIso8601ZuluString(),
        ];
    }
}
