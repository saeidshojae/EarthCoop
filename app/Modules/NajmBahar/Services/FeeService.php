<?php

namespace App\Modules\NajmBahar\Services;

use App\Modules\NajmBahar\Models\Fee;
use App\Models\Setting;
use App\Helpers\BaharMoney;

class FeeService
{
    /**
     * Get membership fee amount
     */
    public function getMembershipFee(): int
    {
        $settings = Setting::firstNajmBaharSettings();
        $configuredAmount = (int) ($settings?->najm_bahar_membership_fee_amount ?? 0);
        if ($configuredAmount > 0) {
            return $configuredAmount;
        }

        $configuredSplitTotal = $this->getMembershipSplitTotal($settings);
        if ($configuredSplitTotal > 0) {
            return $configuredSplitTotal;
        }

        $fee = Fee::where('name', 'membership_fee')
            ->where('is_active', true)
            ->first();

        // اگر کارمزد در دیتابیس تعریف نشده، مقدار پیش‌فرض 12 بهار
        return $fee ? $fee->fixed_amount : BaharMoney::toGolFromBahar(12);
    }

    public function getMembershipSplitTotal(?Setting $settings): int
    {
        if (!$settings) {
            return 0;
        }

        $membershipAmount = (int) ($settings->najm_bahar_membership_fee_membership_amount ?? 0);
        $insuranceAmount = (int) ($settings->najm_bahar_membership_fee_insurance_amount ?? 0);
        $burnAmount = (int) ($settings->najm_bahar_membership_fee_burn_amount ?? 0);

        return $membershipAmount + $insuranceAmount + $burnAmount;
    }

    /**
     * Calculate fee for a transaction
     */
    public function calculateTransactionFee(int $amount, string $transactionType = 'immediate'): int
    {
        $fees = Fee::where('transaction_type', $transactionType)
            ->orWhere('transaction_type', 'all')
            ->where('is_active', true)
            ->get();

        $totalFee = 0;

        foreach ($fees as $fee) {
            $calculatedFee = 0;

            switch ($fee->type) {
                case 'fixed':
                    $calculatedFee = $fee->fixed_amount;
                    break;
                
                case 'percentage':
                    $calculatedFee = intval($amount * ($fee->percentage / 100));
                    break;
                
                case 'combined':
                    $calculatedFee = $fee->fixed_amount + intval($amount * ($fee->percentage / 100));
                    break;
            }

            // اعمال حداقل و حداکثر
            if ($fee->min_amount && $calculatedFee < $fee->min_amount) {
                $calculatedFee = $fee->min_amount;
            }
            
            if ($fee->max_amount && $calculatedFee > $fee->max_amount) {
                $calculatedFee = $fee->max_amount;
            }

            $totalFee += $calculatedFee;
        }

        return $totalFee;
    }

    /**
     * Get all active fees
     */
    public function getActiveFees()
    {
        return Fee::where('is_active', true)->get();
    }
}