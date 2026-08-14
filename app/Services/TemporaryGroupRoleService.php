<?php

namespace App\Services;

use App\Models\GroupUser;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

class TemporaryGroupRoleService
{
    public function apply(
        GroupUser $membership,
        int $targetRole,
        ?CarbonInterface $expiresAt,
        User $actor,
        string $source
    ): GroupUser {
        return DB::transaction(function () use ($membership, $targetRole, $expiresAt, $actor, $source) {
            $locked = GroupUser::query()->lockForUpdate()->findOrFail($membership->id);
            $this->restoreIfExpired($locked);

            if ($locked->role_override_active) {
                $baseline = (int) $locked->role_override_original_role;

                if ($targetRole === $baseline) {
                    $locked->forceFill(['role' => $baseline]);
                    $this->clearOverride($locked);
                    $locked->save();

                    return $locked->refresh();
                }
            } else {
                if ($targetRole === (int) $locked->role) {
                    return $locked;
                }

                $baseline = (int) $locked->role;
            }

            $locked->forceFill([
                'role' => $targetRole,
                'role_override_active' => true,
                'role_override_original_role' => $baseline,
                'role_override_started_at' => now(),
                'role_override_expires_at' => $expiresAt,
                'role_override_changed_by' => $actor->id,
                'role_override_source' => $source,
            ])->save();

            return $locked->refresh();
        });
    }

    public function restoreIfExpired(?GroupUser $membership): ?GroupUser
    {
        if (!$membership || !$membership->role_override_active || !$membership->role_override_expires_at) {
            return $membership;
        }

        if ($membership->role_override_expires_at->isFuture()) {
            return $membership;
        }

        $membership->forceFill(['role' => (int) $membership->role_override_original_role]);
        $this->clearOverride($membership);
        $membership->save();

        return $membership->refresh();
    }

    public function restoreExpiredForGroup(int $groupId): void
    {
        GroupUser::query()
            ->where('group_id', $groupId)
            ->where('role_override_active', true)
            ->whereNotNull('role_override_expires_at')
            ->where('role_override_expires_at', '<=', now())
            ->each(fn (GroupUser $membership) => $this->restoreIfExpired($membership));
    }

    private function clearOverride(GroupUser $membership): void
    {
        $membership->forceFill([
            'role_override_active' => false,
            'role_override_original_role' => null,
            'role_override_started_at' => null,
            'role_override_expires_at' => null,
            'role_override_changed_by' => null,
            'role_override_source' => null,
        ]);
    }
}
