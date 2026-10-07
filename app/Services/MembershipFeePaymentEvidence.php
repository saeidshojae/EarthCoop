<?php

namespace App\Services;

use App\Modules\NajmBahar\Models\Transaction;

class MembershipFeePaymentEvidence
{
    public function hasCompletePaymentEvidence(int $userId, int $paymentYear): bool
    {
        $groups = [];
        foreach (Transaction::query()->where('metadata->type', 'membership_fee')
            ->where('metadata->user_id', $userId)->where('metadata->payment_year', $paymentYear)
            ->where('status', 'completed')->get() as $transaction) {
            $meta = $transaction->metadata ?? [];
            $expected = $meta['expected_breakdown_gol'] ?? null;
            $total = $meta['membership_fee_total_gol'] ?? null;
            $keys = ['operations_salary_gol', 'central_insurance_gol', 'money_destruction_gol'];
            if (! is_array($expected) || count($expected) !== 3 || ! is_int($total) || $total <= 0) {
                continue;
            }
            $remaining = $total;
            $normalized = [];
            foreach ($keys as $key) {
                $value = $expected[$key] ?? null;
                if (! is_int($value) || $value < 0 || $value > $remaining) {
                    continue 2;
                }
                $remaining -= $value;
                $normalized[$key] = $value;
            }
            if ($remaining !== 0 || ! $transaction->from_account_id) {
                continue;
            }
            $group = json_encode([$normalized, $total, (int) $transaction->from_account_id,
                $meta['payment_source'] ?? null, $meta['policy_version_id'] ?? null]);
            $groups[$group]['expected'] = $normalized;
            $groups[$group]['parts'][] = [$meta['split'] ?? null, (int) $transaction->amount];
        }
        foreach ($groups as $group) {
            $actual = [];
            foreach ($group['parts'] as [$part, $amount]) {
                $key = is_string($part) ? $part.'_gol' : '';
                if (! array_key_exists($key, $group['expected']) || $amount <= 0 || isset($actual[$key])) {
                    continue 2;
                }
                $actual[$key] = $amount;
            }
            $positive = array_filter($group['expected'], fn ($amount) => $amount > 0);
            ksort($positive);
            ksort($actual);
            if ($actual === $positive) {
                return true;
            }
        }
        return false;
    }
}
