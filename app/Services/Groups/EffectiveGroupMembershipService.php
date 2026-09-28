<?php

declare(strict_types=1);

namespace App\Services\Groups;

use App\Models\Group;
use App\Models\GroupUser;
use App\Models\User;
use App\Services\TemporaryGroupRoleService;
use Illuminate\Support\Collection;

final class EffectiveGroupMembershipService
{
    public function __construct(private readonly TemporaryGroupRoleService $temporaryRoles)
    {
    }

    public function current(User $user, Group $group): ?GroupUser
    {
        $membership = $this->baseQuery($user)
            ->where('group_id', $group->id)
            ->first();

        return $this->temporaryRoles->restoreIfExpired($membership);
    }

    public function currentForUser(User $user): Collection
    {
        return $this->baseQuery($user)
            ->orderBy('group_id')
            ->get()
            ->map(fn (GroupUser $membership): ?GroupUser => $this->temporaryRoles->restoreIfExpired($membership))
            ->filter()
            ->values();
    }

    private function baseQuery(User $user)
    {
        return GroupUser::query()
            ->where('user_id', $user->id)
            ->where('status', 1)
            ->where(function ($query) {
                $query->whereNull('expired')
                    ->orWhere('expired', 0)
                    ->orWhere('expired', '>', now());
            });
    }
}
