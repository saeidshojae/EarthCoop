<?php

namespace App\Services;

use App\Models\User;
use App\Modules\NajmBahar\Models\Transaction as NajmTransaction;

class MembershipFeeStatusService
{
    public function membershipPaymentYear(User $user): int
    {
        $currentYear = now()->year;
        $currentAnniversary = $user->created_at->copy()->setYear($currentYear);

        return now()->lessThan($currentAnniversary) ? $currentYear - 1 : $currentYear;
    }

    public function hasPaidCurrentMembershipFee(User $user): bool
    {
        $paymentYear = $this->membershipPaymentYear($user);

        if (app(MembershipFeePaymentEvidence::class)->hasCompletePaymentEvidence((int) $user->id, $paymentYear)) {
            return true;
        }

        $actual = NajmTransaction::query()
            ->where('metadata->type', 'membership_fee')
            ->where('metadata->user_id', $user->id)
            ->where('metadata->payment_year', $paymentYear)
            ->where('status', 'completed')
            ->pluck('metadata')
            ->filter(fn ($metadata) => is_array($metadata)
                && ! array_key_exists('expected_breakdown_gol', $metadata)
                && ! array_key_exists('membership_fee_total_gol', $metadata))
            ->map(fn ($metadata) => $metadata['split'] ?? null)
            ->filter()
            ->unique()
            ->values()
            ->toArray();

        sort($actual);

        $canonical = ['central_insurance', 'money_destruction', 'operations_salary'];
        $legacy = ['burn', 'insurance', 'membership'];
        $officialFallback = ['operations_salary'];

        return $actual === $canonical
            || $actual === $legacy
            || $actual === $officialFallback;
    }
}
