<?php

namespace App\Modules\NajmBahar\Services\Api;

use App\Models\User;
use App\Modules\NajmBahar\Models\Account;
use App\Modules\NajmBahar\Models\SubAccount;
use App\Modules\NajmBahar\Services\AccountService;
use App\Modules\NajmBahar\Services\SubAccountService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;

class NajmBaharSubAccountApplicationService
{
    public function __construct(
        private readonly AccountService $accounts,
        private readonly SubAccountService $subAccounts,
    ) {
    }

    public function create(User $user, ?string $name): SubAccount
    {
        $main = $this->mainFor($user);
        $normalized = $this->normalizeOptionalName($name);

        return DB::transaction(
            fn () => $this->subAccounts->createSubAccount(
                (int) $main->id,
                $normalized,
            )
        );
    }

    public function rename(User $user, int $subAccountId, string $name): SubAccount
    {
        $main = $this->mainFor($user);
        $normalized = trim($name);
        if ($normalized === '') {
            throw new \InvalidArgumentException('Sub-account name must not be empty.');
        }

        return DB::transaction(function () use ($main, $subAccountId, $normalized) {
            $sub = SubAccount::query()
                ->whereKey($subAccountId)
                ->where('account_id', (int) $main->id)
                ->where('status', 1)
                ->lockForUpdate()
                ->first();

            if (! $sub instanceof SubAccount) {
                throw (new ModelNotFoundException())->setModel(SubAccount::class);
            }

            $mirror = Account::query()
                ->where('type', 'subaccount')
                ->where('account_number', (string) $sub->sub_account_code)
                ->where('status', 1)
                ->lockForUpdate()
                ->first();

            if (! $mirror instanceof Account) {
                throw (new ModelNotFoundException())->setModel(Account::class);
            }

            if ($sub->name !== $normalized) {
                $sub->name = $normalized;
                $sub->save();
            }

            if ($mirror->name !== $normalized) {
                $mirror->name = $normalized;
                $mirror->save();
            }

            return $sub->fresh();
        });
    }

    private function mainFor(User $user): Account
    {
        $main = $this->accounts->getMainAccountForUser((int) $user->id);

        if (! $main instanceof Account || (int) $main->user_id !== (int) $user->id) {
            throw (new ModelNotFoundException())->setModel(Account::class);
        }

        return $main;
    }

    private function normalizeOptionalName(?string $name): ?string
    {
        if ($name === null) {
            return null;
        }

        $normalized = trim($name);

        return $normalized === '' ? null : $normalized;
    }
}
