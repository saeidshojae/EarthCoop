<?php

namespace App\Services;

use App\Models\NajmBaharAuditLog;
use App\Models\Group;
use App\Models\User;

class NajmBaharAuditLogger
{
    public static function log(array $payload): NajmBaharAuditLog
    {
        $request = request();

        $payload['ip_address'] = $payload['ip_address'] ?? ($request ? $request->ip() : null);
        $payload['user_agent'] = $payload['user_agent'] ?? ($request ? $request->userAgent() : null);

        return NajmBaharAuditLog::create($payload);
    }

    public static function logGroupAction(Group $group, ?User $actor, array $payload): NajmBaharAuditLog
    {
        $payload['group_id'] = $group->id;
        $payload['actor_user_id'] = $actor?->id;
        $payload['actor_role'] = $payload['actor_role'] ?? null;

        return self::log($payload);
    }
}
