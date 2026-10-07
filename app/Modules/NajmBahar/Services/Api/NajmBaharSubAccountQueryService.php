<?php

namespace App\Modules\NajmBahar\Services\Api;

use App\Models\User;
use App\Modules\NajmBahar\Models\Account;
use App\Modules\NajmBahar\Models\SubAccount;
use App\Modules\NajmBahar\Services\AccountBalanceService;
use App\Modules\NajmBahar\Services\AccountService;
use App\Modules\NajmBahar\Services\ActiveBaharReservationService;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class NajmBaharSubAccountQueryService
{
    public const CONTRACT_VERSION = 1;

    public function __construct(
        private readonly AccountService $accounts,
        private readonly AccountBalanceService $balances,
        private readonly ActiveBaharReservationService $reservations,
    ) {
    }

    public function forUser(User $user): array
    {
        $main = $this->accounts->getMainAccountForUser((int) $user->id);
        if (! $main instanceof Account || (int) $main->user_id !== (int) $user->id) {
            throw (new ModelNotFoundException())->setModel(Account::class);
        }

        $mainBalance = $this->balances->local($main);

        $subaccounts = SubAccount::query()
            ->where('account_id', (int) $main->id)
            ->where('status', 1)
            ->orderBy('id')
            ->get()
            ->map(fn (SubAccount $sub) => $this->projectSubAccount($sub))
            ->filter()
            ->values()
            ->all();

        return [
            'internal_transfer_contract_version' => self::CONTRACT_VERSION,
            'main' => [
                'account_id' => (int) $main->id,
                'account_number' => (string) $main->account_number,
                'name' => (string) $main->name,
                'active_gol' => max(0, (int) $mainBalance['active']),
                'active_available_gol' => max(
                    0,
                    min(
                        (int) $mainBalance['active'],
                        $this->reservations->availableActive($main),
                    ),
                ),
                'dim_available_gol' => max(0, (int) $mainBalance['dim_available']),
                'dim_committed_gol' => max(0, (int) $mainBalance['dim_committed']),
            ],
            'subaccounts' => $subaccounts,
        ];
    }

    public function oneForUser(User $user, int $subAccountId): array
    {
        $main = $this->accounts->getMainAccountForUser((int) $user->id);
        if (! $main instanceof Account || (int) $main->user_id !== (int) $user->id) {
            throw (new ModelNotFoundException())->setModel(Account::class);
        }

        $sub = SubAccount::query()
            ->whereKey($subAccountId)
            ->where('account_id', (int) $main->id)
            ->where('status', 1)
            ->first();

        if (! $sub instanceof SubAccount) {
            throw (new ModelNotFoundException())->setModel(SubAccount::class);
        }

        $projected = $this->projectSubAccount($sub);
        if ($projected === null) {
            throw (new ModelNotFoundException())->setModel(Account::class);
        }

        return $projected;
    }

    private function projectSubAccount(SubAccount $sub): ?array
    {
        $mirror = Account::query()
            ->where('type', 'subaccount')
            ->where('account_number', (string) $sub->sub_account_code)
            ->where('status', 1)
            ->first();

        if (! $mirror instanceof Account) {
            return null;
        }

        $active = max(0, (int) ($sub->balance_active ?? 0));
        $availableActive = min(
            $active,
            $this->reservations->availableActive($mirror),
        );

        return [
            'sub_account_id' => (int) $sub->id,
            'account_id' => (int) $mirror->id,
            'account_number' => (string) $sub->sub_account_code,
            'name' => (string) $sub->name,
            'status' => (int) $sub->status,
            'active_gol' => $active,
            'active_available_gol' => max(0, $availableActive),
            'dim_available_gol' => max(0, (int) ($sub->balance_faded ?? 0)),
        ];
    }
}
