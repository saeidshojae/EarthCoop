<?php

namespace App\Modules\NajmBahar\Services\Api;

use App\Models\User;
use App\Modules\NajmBahar\Models\Account;
use App\Modules\NajmBahar\Models\SubAccount;
use App\Modules\NajmBahar\Models\Transaction;
use App\Modules\NajmBahar\Services\TransactionService;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class NajmBaharTransferApplicationService
{
    public function __construct(
        private readonly TransactionService $transactions,
    ) {
    }

    /**
     * Execute a member-initiated transfer through the canonical Najm Bahar
     * transaction boundary. Account ownership and authority are resolved only
     * from server-side persisted relationships; no client authority flags are
     * accepted or forwarded.
     *
     * @return array{transaction: Transaction, source: Account}
     */
    public function transfer(
        User $user,
        int $sourceAccountId,
        string $destinationAccountNumber,
        int $amountGol,
        string $balanceBucket,
        string $transportIdempotencyKey,
        ?string $description = null,
    ): array {
        $source = Account::query()->find($sourceAccountId);
        if (! $source instanceof Account || ! $this->isOwnedBy($source, $user)) {
            throw (new ModelNotFoundException())->setModel(Account::class);
        }

        $destination = Account::query()
            ->where('account_number', $destinationAccountNumber)
            ->first();
        if (! $destination instanceof Account) {
            throw (new ModelNotFoundException())->setModel(Account::class);
        }

        $balanceType = match ($balanceBucket) {
            'active' => 'active',
            'dim' => 'faded',
            default => throw new \InvalidArgumentException('Unsupported balance bucket.'),
        };

        $domainKey = implode('-', [
            'api-v1-transfer',
            'user',
            (int) $user->id,
            hash('sha256', $transportIdempotencyKey),
        ]);

        $transaction = $this->transactions->transfer(
            (string) $source->account_number,
            (string) $destination->account_number,
            $amountGol,
            $description ?? 'Najm Bahar member transfer',
            [
                'api_v1_operation' => 'member_transfer',
                'api_v1_actor_id' => (int) $user->id,
            ],
            $domainKey,
            $balanceType,
            'api_v1_transfer',
        );

        return [
            'transaction' => $transaction->fresh(),
            'source' => $source->fresh(),
        ];
    }

    private function isOwnedBy(Account $account, User $user): bool
    {
        if ((int) ($account->user_id ?? 0) === (int) $user->id) {
            return true;
        }

        if ($account->type !== 'subaccount') {
            return false;
        }

        $subAccount = SubAccount::query()
            ->where('sub_account_code', $account->account_number)
            ->first();
        if (! $subAccount instanceof SubAccount) {
            return false;
        }

        return Account::query()
            ->whereKey((int) $subAccount->account_id)
            ->where('user_id', (int) $user->id)
            ->exists();
    }
}
