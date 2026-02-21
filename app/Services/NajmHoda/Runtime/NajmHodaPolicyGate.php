<?php

namespace App\Services\NajmHoda\Runtime;

use App\Models\Group;
use App\Models\GroupUser;
use App\Models\NajmHodaGroupActionLog;

class NajmHodaPolicyGate
{
    public function ensureActionExecutionAllowed(Group $group, int $requesterUserId, array $policy): array
    {
        if (!(bool) ($policy['enabled'] ?? false)) {
            return [
                'allowed' => false,
                'decision' => 'skipped',
                'reason' => 'action_executor_disabled',
                'group_reply' => 'اجرای اکشن‌های گروهی توسط نجم هدا در این گروه غیرفعال است.',
                'context' => ['policy' => 'action_executor.enabled'],
            ];
        }

        if (!$this->isUserAllowedByRoles($group, $requesterUserId, (array) ($policy['permitted_roles'] ?? [2, 3]))) {
            return [
                'allowed' => false,
                'decision' => 'skipped',
                'reason' => 'action_requester_not_allowed',
                'group_reply' => 'برای اجرای اکشن‌های گروهی دسترسی لازم را ندارید.',
                'context' => ['requester_user_id' => $requesterUserId],
            ];
        }

        $maxActionsPerHour = (int) ($policy['max_actions_per_hour'] ?? 6);
        $hourlyActions = NajmHodaGroupActionLog::query()
            ->where('group_id', $group->id)
            ->where('action_type', 'group_action_execute')
            ->where('decision', 'executed')
            ->where('created_at', '>=', now()->subHour())
            ->count();

        if ($hourlyActions >= $maxActionsPerHour) {
            return [
                'allowed' => false,
                'decision' => 'skipped',
                'reason' => 'action_rate_limited',
                'group_reply' => 'سقف اجرای اکشن‌های خودکار این گروه در این ساعت پر شده است.',
                'context' => ['max_actions_per_hour' => $maxActionsPerHour],
            ];
        }

        return ['allowed' => true];
    }

    public function ensureModerationAllowed(Group $group, int $requesterUserId, array $permittedRoles = [2, 3]): array
    {
        if (!$this->isUserAllowedByRoles($group, $requesterUserId, $permittedRoles)) {
            return [
                'allowed' => false,
                'decision' => 'skipped',
                'reason' => 'moderation_requester_not_allowed',
                'group_reply' => 'برای مدیریت پاکسازی پیام‌های گروه، دسترسی مدیر/بازرس لازم است.',
                'context' => ['requester_user_id' => $requesterUserId],
            ];
        }

        return ['allowed' => true];
    }

    public function shouldProposeBeforeExecute(array $policy): bool
    {
        return (bool) ($policy['propose_before_execute'] ?? false);
    }

    public function isCapabilityEnabled(array $policy, string $capability): bool
    {
        $map = [
            'create_post' => 'allow_create_post',
            'create_poll' => 'allow_create_poll',
            'create_comment' => 'allow_create_comment',
            'react_message' => 'allow_react_message',
            'react_post' => 'allow_react_post',
            'react_comment' => 'allow_react_comment',
        ];

        $key = $map[$capability] ?? null;
        if ($key === null) {
            return false;
        }

        return (bool) ($policy[$key] ?? true);
    }

    public function isUserAllowedByRoles(Group $group, int $requesterUserId, array $permittedRoles): bool
    {
        $role = GroupUser::query()
            ->where('group_id', $group->id)
            ->where('user_id', $requesterUserId)
            ->where('status', 1)
            ->value('role');

        $allowedRoles = array_map(static fn ($r): int => (int) $r, $permittedRoles);
        return in_array((int) $role, $allowedRoles, true);
    }
}

