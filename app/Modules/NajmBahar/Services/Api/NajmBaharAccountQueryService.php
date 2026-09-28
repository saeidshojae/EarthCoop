<?php

namespace App\Modules\NajmBahar\Services\Api;

use App\Models\User;
use App\Modules\NajmBahar\Models\Account;
use App\Modules\NajmBahar\Services\AccountBalanceService;
use App\Modules\NajmBahar\Services\AccountService;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class NajmBaharAccountQueryService
{
    public function __construct(
        private readonly AccountService $accounts,
        private readonly AccountBalanceService $balances,
    ) {
    }

    public function mainFor(User $user): Account
    {
        $account = $this->accounts->getMainAccountForUser((int) $user->id);

        if (! $account instanceof Account || (int) $account->user_id !== (int) $user->id) {
            throw (new ModelNotFoundException())->setModel(Account::class);
        }

        return $account;
    }

    public function ownedBy(User $user, int $accountId): ?Account
    {
        return Account::query()
            ->whereKey($accountId)
            ->where('user_id', (int) $user->id)
            ->first();
    }

    public function balance(Account $account): array
    {
        return [
            'local' => $this->project($this->balances->local($account)),
            'aggregate' => $this->project($this->balances->aggregate($account)),
        ];
    }

    private function project(array $balance): array
    {
        return [
            'active_gol' => (int) $balance['active'],
            'dim_available_gol' => (int) $balance['dim_available'],
            'dim_committed_gol' => (int) $balance['dim_committed'],
            'dim_total_gol' => (int) $balance['dim'],
            'total_gol' => (int) $balance['total'],
        ];
    }
}
