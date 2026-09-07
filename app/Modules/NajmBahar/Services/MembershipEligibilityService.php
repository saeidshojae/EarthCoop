<?php

namespace App\Modules\NajmBahar\Services;

use App\Models\User;
use App\Services\ProfileCompletionService;

class MembershipEligibilityService
{
    public function __construct(
        private readonly ProfileCompletionService $profileCompletionService
    ) {
    }

    public function isEligibleForInitialMembershipCredit(User $user): bool
    {
        if ($user->isSystemIdentity()) {
            return false;
        }

        if ((string) $user->status !== 'active') {
            return false;
        }

        if (is_null($user->terms_accepted_at) || is_null($user->email_verified_at)) {
            return false;
        }

        return $this->profileCompletionService->isComplete($user);
    }
}
