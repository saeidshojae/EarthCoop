<?php

namespace App\Modules\NajmBahar\Services;

use App\Models\Group;
use App\Modules\NajmBahar\Models\Account;
use App\Modules\NajmBahar\Models\SubAccount;
use App\Modules\NajmBahar\Models\Transaction;
use App\Modules\NajmBahar\Services\TransactionService;
use Illuminate\Support\Facades\DB;

class AccountService
{
    /**
     * Create a main account for user without altering legacy Najm implementation.
     * Returns created Account model.
     */
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

    /**
     * Check if user has a main account
     */
    public function hasMainAccount(int $userId): bool
    {
        $accountNumber = AccountNumberService::makeMainAccountNumberForUser($userId);
        return Account::where('account_number', $accountNumber)->exists();
    }

    /**
     * Get main account for user
     */
    public function getMainAccountForUser(int $userId): ?Account
    {
        $accountNumber = AccountNumberService::makeMainAccountNumberForUser($userId);
        return Account::where('account_number', $accountNumber)->first();
    }

    /**
     * Get system account
     */
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
     * Ensure system account has the default three subaccounts.
     */
    public function ensureDefaultSystemSubAccounts(Account $systemAccount): void
    {
        $defaults = [
            1 => 'حساب حق عضویت ارثکوپ',
            2 => 'حساب بیمه پایه همگانی',
            0 => 'حساب امحای پول',
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
        ]);

        if ($account->name !== $subAccount->name) {
            $account->name = $subAccount->name;
            $account->save();
        }

        if ($subAccount->balance !== $account->balance) {
            $subAccount->balance = $account->balance;
            $subAccount->save();
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

    /**
     * Create a simple immediate transaction between accounts.
     * This is a skeleton; business rules (fees, checks) must be implemented.
     */
    public function createTransaction(array $payload): Transaction
    {
        // for now, use simple create; production should call TransactionService->transfer for atomic operations
        return Transaction::create($payload);
    }
}
