<?php

namespace App\Services;

use App\Models\User;
use App\Modules\NajmBahar\Services\AccountService;

class MembershipParticipationEligibilityService
{
    public const NO_NAJM_BAHAR_ACCOUNT = 'no_najm_bahar_account';
    public const MEMBERSHIP_FEE_DUE = 'membership_fee_due';
    public const ELIGIBLE = 'eligible';

    public function __construct(
        protected AccountService $accountService,
        protected MembershipFeeStatusService $membershipFeeStatus,
    ) {
    }

    public function status(User $user): string
    {
        $account = $this->accountService->getMainAccountForUser($user->id);
        if (! $account) {
            return self::NO_NAJM_BAHAR_ACCOUNT;
        }

        return $this->hasPaidCurrentMembershipFee($user)
            ? self::ELIGIBLE
            : self::MEMBERSHIP_FEE_DUE;
    }

    public function isEligible(User $user): bool
    {
        return $this->status($user) === self::ELIGIBLE;
    }

    public function membershipPaymentYear(User $user): int
    {
        return $this->membershipFeeStatus->membershipPaymentYear($user);
    }

    public function hasPaidCurrentMembershipFee(User $user): bool
    {
        return $this->membershipFeeStatus->hasPaidCurrentMembershipFee($user);
    }
}
