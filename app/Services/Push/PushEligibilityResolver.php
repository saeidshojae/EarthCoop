<?php

namespace App\Services\Push;

use App\Models\User;
use Illuminate\Support\Collection;

class PushEligibilityResolver
{
    public function eligibleFor(User $user): Collection
    {
        return $user->nativeDevices()
            ->whereNull('revoked_at')
            ->where('push_capable', true)
            ->whereNull('push_disabled_at')
            ->whereNotNull('push_provider')
            ->whereNotNull('push_token_hash')
            ->get();
    }
}
