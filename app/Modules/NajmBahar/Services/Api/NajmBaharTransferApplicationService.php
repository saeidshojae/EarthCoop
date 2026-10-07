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
        private readonly NajmBaharTransferCapabilityService $capability,
        private readonly NajmBaharTransferDestinationService $destinations,
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
        ?array $expected = null,
    ): array {
        $source = Account::query()->find($sourceAccountId);
        if (! $source instanceof Account || ! $this->isOwnedBy($source, $user)) {
            throw (new ModelNotFoundException())->setModel(Account::class);
        }

        if ($expected !== null) {
            $this->assertStrictNativeIntent(
                $user,
                $source,
                $destinationAccountNumber,
                $amountGol,
                $balanceBucket,
                $expected,
            );
            $destination = $this->destinations->verify(
                $user,
                (string) $expected['destination_token'],
                $destinationAccountNumber,
            );
        } else {
            $destination = Account::query()
                ->where('account_number', $destinationAccountNumber)
                ->first();
            if (! $destination instanceof Account) {
                throw (new ModelNotFoundException())->setModel(Account::class);
            }
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
                // Server-minted only. Public request validation rejects client
                // metadata/authority fields, so this cannot be forged by the
                // mobile caller to reopen the retired generic child fallback.
                'trusted_canonical_subaccount_transfer' => true,
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

    private function assertStrictNativeIntent(
        User $user,
        Account $source,
        string $destinationAccountNumber,
        int $amountGol,
        string $balanceBucket,
        array $expected,
    ): void {
        if ($balanceBucket !== 'active') {
            throw new NajmBaharTransferException(
                'transfer_not_allowed',
                'External native transfers use Active Bahar only.',
                409,
            );
        }

        $capability = $this->capability->forUser($user);
        if (! ($capability['external_transfer_enabled'] ?? false)) {
            throw new NajmBaharTransferException(
                'transfer_not_allowed',
                'External transfers are not enabled by the current policy.',
                409,
            );
        }

        $row = collect($capability['sources'] ?? [])
            ->first(fn (array $candidate) => (int) ($candidate['account_id'] ?? 0) === (int) $source->id);

        if (! is_array($row)
            || ($expected['transfer_contract_version'] ?? null) !== 1
            || ($expected['source_account_number'] ?? null) !== (string) $source->account_number
            || ($row['account_number'] ?? null) !== (string) $source->account_number
            || ($expected['source_active_available_gol'] ?? null) !== ($row['active_available_gol'] ?? null)) {
            throw new NajmBaharTransferException(
                'transfer_terms_changed',
                'Transfer terms changed; review the current source before sending.',
                409,
            );
        }

        if ($amountGol > (int) $row['active_available_gol']) {
            throw new NajmBaharTransferException(
                'insufficient_available_funds',
                'Available Active balance is insufficient for this transfer.',
                409,
            );
        }

        if (trim($destinationAccountNumber) === '') {
            throw new NajmBaharTransferException(
                'transfer_destination_changed',
                'The transfer destination changed; review it again before sending.',
                409,
            );
        }
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
