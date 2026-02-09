<?php

namespace App\Modules\NajmBahar\Adapters;

use App\Models\Spring;
use App\Modules\NajmBahar\Services\AccountNumberService;
use App\Modules\NajmBahar\Models\Account;
use App\Modules\NajmBahar\Models\SubAccount;
use App\Modules\NajmBahar\Services\TransactionService;
use App\Modules\NajmBahar\Services\FeeService;
use App\Modules\NajmBahar\Models\LedgerEntry;
use App\Models\Setting;
use App\Helpers\BaharMoney;
use Illuminate\Support\Facades\Log;

class LegacyNajmAdapter
{
    /**
     * Mirror a legacy Spring model creation into NajmBahar module.
     * This runs after the legacy Spring record is created and should NOT
     * mutate legacy records. It attempts to create Najm accounts and
     * replicate initial funding and membership fee.
     */
    public static function onSpringCreated(Spring $spring)
    {
        try {
            $userId = $spring->user_id;

            // ensure system account exists
            $systemNumber = AccountNumberService::makeSystemAccountNumber();
            $system = Account::firstOrCreate([
                'account_number' => $systemNumber,
            ], [
                'name' => 'EarthCoop System Main Account',
                'type' => 'system',
                'balance' => 0,
            ]);

            // ensure membership subaccount exists
            $membershipCode = $systemNumber . '-001';
            $membership = SubAccount::firstOrCreate([
                'sub_account_code' => $membershipCode,
            ], [
                'account_id' => $system->id,
                'name' => 'Membership Fees',
                'balance' => 0,
            ]);

            // create user main account if not exists
            $userAccountNumber = AccountNumberService::makeMainAccountNumberForUser($userId);
            $userAcc = Account::firstOrCreate(['account_number' => $userAccountNumber], [
                'user_id' => $userId,
                'name' => 'NajmBahar Account',
                'type' => 'user',
                'balance' => 0,
            ]);

            $txService = new TransactionService();
            $feeService = new FeeService();
            $settings = Setting::firstNajmBaharSettings();
            $initialAmount = (int) ($settings?->najm_bahar_initial_amount ?? BaharMoney::toGolFromBahar(10000));
            $membershipFee = $feeService->getMembershipFee();

            // Credit initial 10000 bex to user from system
            // Use idempotency key so repeated events don't double-credit
            try {
                // avoid duplicate funding by checking ledger entries for this user
                $userLedgerExists = LedgerEntry::where('account_id', $userAcc->id)
                    ->where('amount', $initialAmount)
                    ->where('entry_type', 'credit')
                    ->exists();

                if (! $userLedgerExists) {
                    $idempInit = 'spring-init-' . $spring->id;
                    // also check transactions metadata for existing idempotency key (SQLite JSON)
                    $exists = \Illuminate\Support\Facades\DB::table('najm_transactions')
                        ->whereRaw("json_extract(metadata, '$.idempotency_key') = ?", [$idempInit])
                        ->exists();

                    if (! $exists) {
                        $txService->transfer($systemNumber, $userAccountNumber, $initialAmount, 'Initial funding mirrored from legacy Spring', array_merge(['legacy_spring_id' => $spring->id], []), $idempInit);
                    }
                }
            } catch (\Throwable $e) {
                // log and continue
                Log::warning('NajmBahar adapter: initial funding failed: ' . $e->getMessage());
            }

            // Debit membership fee from user to system subaccounts
            try {
                $membershipAccount = $settings?->najm_bahar_membership_fee_account ?? $membershipCode;
                $insuranceAccount = $settings?->najm_bahar_membership_fee_insurance_account ?? ($systemNumber . '-002');
                $burnAccount = $settings?->najm_bahar_membership_fee_burn_account ?? ($systemNumber . '-000');

                $membershipAmount = (int) ($settings?->najm_bahar_membership_fee_membership_amount ?? BaharMoney::toGolFromBahar(6));
                $insuranceAmount = (int) ($settings?->najm_bahar_membership_fee_insurance_amount ?? BaharMoney::toGolFromBahar(3));
                $burnAmount = (int) ($settings?->najm_bahar_membership_fee_burn_amount ?? BaharMoney::toGolFromBahar(3));

                $splitTotal = $membershipAmount + $insuranceAmount + $burnAmount;
                if ($splitTotal <= 0 || $splitTotal !== $membershipFee) {
                    $splitTotal = $membershipFee;
                    $membershipAmount = $membershipFee;
                    $insuranceAmount = 0;
                    $burnAmount = 0;
                }

                $targets = [
                    [$membershipAccount, $membershipAmount, 'membership'],
                    [$insuranceAccount, $insuranceAmount, 'insurance'],
                    [$burnAccount, $burnAmount, 'burn'],
                ];

                foreach ($targets as [$targetAccount, $amount, $suffix]) {
                    if ($amount <= 0) {
                        continue;
                    }

                    $idempFee = 'spring-membership-' . $spring->id . '-' . $suffix;
                    $existsFee = \Illuminate\Support\Facades\DB::table('najm_transactions')
                        ->whereRaw("json_extract(metadata, '$.idempotency_key') = ?", [$idempFee])
                        ->exists();

                    if (! $existsFee) {
                        $txService->transfer($userAccountNumber, $targetAccount, $amount, 'Membership fee mirrored from legacy Spring', array_merge(['legacy_spring_id' => $spring->id, 'split' => $suffix], []), $idempFee);
                    }
                }
            } catch (\Throwable $e) {
                Log::warning('NajmBahar adapter: membership fee mirror failed: ' . $e->getMessage());
            }

        } catch (\Throwable $e) {
            Log::error('NajmBahar adapter failed: ' . $e->getMessage());
        }
    }
}
