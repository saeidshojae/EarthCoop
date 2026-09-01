<?php

namespace App\Services;

use App\Models\User;
use App\Modules\NajmBahar\Models\Transaction as NajmTransaction;
use App\Modules\NajmBahar\Services\AccountService;

class MembershipParticipationEligibilityService
{
    public const NO_NAJM_BAHAR_ACCOUNT = 'no_najm_bahar_account';
    public const MEMBERSHIP_FEE_DUE = 'membership_fee_due';
    public const ELIGIBLE = 'eligible';

    public function __construct(
        protected AccountService $accountService,
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
        $currentYear = now()->year;
        $currentAnniversary = $user->created_at->copy()->setYear($currentYear);

        return now()->lessThan($currentAnniversary) ? $currentYear - 1 : $currentYear;
    }

    public function hasPaidCurrentMembershipFee(User $user): bool
    {
        $paymentYear = $this->membershipPaymentYear($user);

        $actual = NajmTransaction::where('metadata->type', 'membership_fee')
            ->where('metadata->user_id', $user->id)
            ->where('metadata->payment_year', $paymentYear)
            ->pluck('metadata')
            ->map(fn ($metadata) => $metadata['split'] ?? null)
            ->filter()
            ->unique()
            ->values()
            ->toArray();

        $current = ['operations_salary', 'central_insurance', 'money_destruction'];
        $legacy = ['membership', 'insurance', 'burn'];

        sort($actual);
        sort($current);
        sort($legacy);

        return $actual === $current || $actual === $legacy;
    }
}
