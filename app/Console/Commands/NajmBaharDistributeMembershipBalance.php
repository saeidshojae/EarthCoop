<?php

namespace App\Console\Commands;

use App\Models\Setting;
use App\Modules\NajmBahar\Models\SubAccount;
use App\Modules\NajmBahar\Services\AccountNumberService;
use App\Modules\NajmBahar\Services\AccountService;
use App\Modules\NajmBahar\Services\TransactionService;
use App\Helpers\BaharMoney;
use Illuminate\Console\Command;

class NajmBaharDistributeMembershipBalance extends Command
{
    protected $signature = 'najm-bahar:distribute-membership-balance {amount : Total amount to distribute} {--dry-run : Show planned transfers without applying}';

    protected $description = 'Distribute a total membership-fee amount from the system account into its subaccounts based on current split settings.';

    public function handle(AccountService $accountService, TransactionService $transactionService): int
    {
        $amountRaw = (string) $this->argument('amount');

        try {
            $amount = BaharMoney::parseToGol($amountRaw);
        } catch (\InvalidArgumentException $e) {
            $this->error('Amount must be a positive decimal value (e.g., 10.30).');
            return self::FAILURE;
        }

        if ($amount <= 0) {
            $this->error('Amount must be a positive decimal value (e.g., 10.30).');
            return self::FAILURE;
        }

        $settings = Setting::firstNajmBaharSettings();
        $membershipAmount = (int) ($settings?->najm_bahar_membership_fee_membership_amount ?? BaharMoney::toGolFromBahar(6));
        $insuranceAmount = (int) ($settings?->najm_bahar_membership_fee_insurance_amount ?? BaharMoney::toGolFromBahar(3));
        $burnAmount = (int) ($settings?->najm_bahar_membership_fee_burn_amount ?? BaharMoney::toGolFromBahar(3));
        $totalSplit = $membershipAmount + $insuranceAmount + $burnAmount;

        if ($totalSplit <= 0) {
            $this->error('Membership split total must be greater than 0.');
            return self::FAILURE;
        }

        if ($amount % $totalSplit !== 0) {
            $this->error("Amount {$amount} is not divisible by split total {$totalSplit}.");
            return self::FAILURE;
        }

        $multiplier = (int) ($amount / $totalSplit);
        $membershipTotal = $membershipAmount * $multiplier;
        $insuranceTotal = $insuranceAmount * $multiplier;
        $burnTotal = $burnAmount * $multiplier;

        $systemAccount = $accountService->getSystemAccount();
        $accountService->ensureDefaultSystemSubAccounts($systemAccount);

        $membershipAccount = AccountNumberService::makeSubAccountCode($systemAccount->account_number, 1);
        $insuranceAccount = AccountNumberService::makeSubAccountCode($systemAccount->account_number, 2);
        $burnAccount = AccountNumberService::makeSubAccountCode($systemAccount->account_number, 0);

        $this->info('Planned transfers:');
        $this->line("- Membership: " . BaharMoney::formatDecimal($membershipTotal) . " -> {$membershipAccount}");
        $this->line("- Insurance: " . BaharMoney::formatDecimal($insuranceTotal) . " -> {$insuranceAccount}");
        $this->line("- Burn: " . BaharMoney::formatDecimal($burnTotal) . " -> {$burnAccount}");

        if ($this->option('dry-run')) {
            return self::SUCCESS;
        }

        if ($membershipTotal > 0) {
            $transactionService->transfer(
                $systemAccount->account_number,
                $membershipAccount,
                $membershipTotal,
                'انتقال حق عضویت انباشته به حساب عضویت',
                ['type' => 'membership_fee_redistribute', 'split' => 'membership']
            );
        }

        if ($insuranceTotal > 0) {
            $transactionService->transfer(
                $systemAccount->account_number,
                $insuranceAccount,
                $insuranceTotal,
                'انتقال حق عضویت انباشته به حساب بیمه',
                ['type' => 'membership_fee_redistribute', 'split' => 'insurance']
            );
        }

        if ($burnTotal > 0) {
            $transactionService->transfer(
                $systemAccount->account_number,
                $burnAccount,
                $burnTotal,
                'انتقال حق عضویت انباشته به حساب امحا',
                ['type' => 'membership_fee_redistribute', 'split' => 'burn']
            );
        }

        $this->syncSubAccountBalances($systemAccount->id, $accountService);

        $this->info('Distribution completed.');

        return self::SUCCESS;
    }

    private function syncSubAccountBalances(int $systemAccountId, AccountService $accountService): void
    {
        $subAccounts = SubAccount::where('account_id', $systemAccountId)->get();

        foreach ($subAccounts as $subAccount) {
            $account = $accountService->ensureSubAccountAccount($subAccount);
            if ($subAccount->balance !== $account->balance) {
                $subAccount->balance = $account->balance;
                $subAccount->save();
            }
        }
    }
}
