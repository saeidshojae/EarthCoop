<?php

namespace App\Modules\NajmBahar\Services;

use App\Models\Group;
use App\Modules\NajmBahar\Models\Account;
use App\Modules\NajmBahar\Models\SubAccount;
use App\Modules\NajmBahar\Models\Transaction;

class AccountService
{
    public function createMainAccountForUser(int $userId, string $name = 'NajmBahar Account'): Account
    {
        $accountNumber = AccountNumberService::makeMainAccountNumberForUser($userId);

        return Account::create([
            'account_number' => $accountNumber,
            'user_id' => $userId,
            'name' => $name,
            'type' => 'user',
            'balance' => 0,
        ]);
    }

    public function hasMainAccount(int $userId): bool
    {
        $accountNumber = AccountNumberService::makeMainAccountNumberForUser($userId);
        return Account::where('account_number', $accountNumber)->exists();
    }

    public function getMainAccountForUser(int $userId): ?Account
    {
        $accountNumber = AccountNumberService::makeMainAccountNumberForUser($userId);
        return Account::where('account_number', $accountNumber)->first();
    }

    public function getSystemAccount(): Account
    {
        $systemNumber = AccountNumberService::makeSystemAccountNumber();

        $account = Account::firstOrCreate([
            'account_number' => $systemNumber,
        ], [
            'name' => 'EarthCoop System Main Account',
            'type' => 'system',
            'balance' => 0,
        ]);

        $this->ensureDefaultSystemSubAccounts($account);

        return $account;
    }

    public function ensureLegalEntityAccountForGroup(Group $group): Account
    {
        $accountNumber = AccountNumberService::makeLegalEntityAccountNumberForGroup($group->id);

        $account = Account::firstOrCreate([
            'account_number' => $accountNumber,
        ], [
            'name' => 'حساب گروه ' . $group->name,
            'type' => 'legal_entity',
            'balance' => 0,
            'meta' => ['group_id' => $group->id],
            'status' => 1,
        ]);

        $meta = (array) ($account->meta ?? []);
        if (($meta['group_id'] ?? null) !== $group->id) {
            $meta['group_id'] = $group->id;
            $account->meta = $meta;
        }

        if ($account->name !== 'حساب گروه ' . $group->name) {
            $account->name = 'حساب گروه ' . $group->name;
        }

        if ($account->type !== 'legal_entity') {
            $account->type = 'legal_entity';
        }

        if ($account->isDirty()) {
            $account->save();
        }

        return $account;
    }

    /**
     * Ensure system account has the default treasury sub-accounts.
     */
    public function ensureDefaultSystemSubAccounts(Account $systemAccount): void
    {
        $defaults = [
            1 => 'صندوق حقوق و هزینه‌های EarthCoop',
            2 => 'صندوق بیمه مرکزی',
            0 => 'صندوق امحای پول',
            3 => 'صندوق مالیات پول راکد',
        ];

        foreach ($defaults as $index => $name) {
            $code = AccountNumberService::makeSubAccountCode($systemAccount->account_number, $index);

            $subAccount = SubAccount::firstOrCreate([
                'sub_account_code' => $code,
            ], [
                'account_id' => $systemAccount->id,
                'name' => $name,
                'balance' => 0,
                'status' => 1,
            ]);

            if ($subAccount->account_id !== $systemAccount->id || $subAccount->name !== $name) {
                $subAccount->account_id = $systemAccount->id;
                $subAccount->name = $name;
                $subAccount->save();
            }

            $this->ensureSubAccountAccount($subAccount);
        }
    }

    public function ensureSubAccountAccount(SubAccount $subAccount): Account
    {
        $account = Account::firstOrCreate([
            'account_number' => $subAccount->sub_account_code,
        ], [
            'name' => $subAccount->name,
            'type' => 'subaccount',
            'balance' => $subAccount->balance,
            'balance_active' => $subAccount->balance_active ?? 0,
            'balance_faded' => $subAccount->balance_faded ?? 0,
        ]);

        if ($account->name !== $subAccount->name) {
            $account->name = $subAccount->name;
            $account->save();
        }

        $subTotal = intval($subAccount->balance_active ?? 0) + intval($subAccount->balance_faded ?? 0);
        if ((int) $subAccount->balance !== $subTotal) {
            $subAccount->balance = $subTotal;
            $subAccount->save();
        }

        $accountNeedsUpdate = (int) $account->balance !== (int) $subAccount->balance
            || (int) ($account->balance_active ?? 0) !== (int) ($subAccount->balance_active ?? 0)
            || (int) ($account->balance_faded ?? 0) !== (int) ($subAccount->balance_faded ?? 0);

        if ($accountNeedsUpdate) {
            $account->balance = (int) $subAccount->balance;
            $account->balance_active = (int) ($subAccount->balance_active ?? 0);
            $account->balance_faded = (int) ($subAccount->balance_faded ?? 0);
            $account->save();
        }

        return $account;
    }

    public function getSystemSubAccountByCode(string $subAccountCode): ?SubAccount
    {
        $systemAccount = $this->getSystemAccount();
        $this->ensureDefaultSystemSubAccounts($systemAccount);

        return SubAccount::where('account_id', $systemAccount->id)
            ->where('sub_account_code', $subAccountCode)
            ->first();
    }

    public function createTransaction(array $payload): Transaction
    {
        return Transaction::create($payload);
    }
}
