<?php

namespace App\Modules\NajmBahar\Services\Api;

use App\Models\User;
use App\Modules\NajmBahar\Models\Account;
use App\Modules\NajmBahar\Models\SubAccount;
use App\Modules\NajmBahar\Models\Transaction;
use App\Modules\NajmBahar\Services\AccountService;
use App\Modules\NajmBahar\Services\InternalAccountTransferService;
use App\Modules\NajmBahar\Services\InternalSubAccountTransferService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;

class NajmBaharInternalTransferApplicationService
{
    public function __construct(
        private readonly AccountService $accounts,
        private readonly NajmBaharSubAccountQueryService $query,
        private readonly InternalAccountTransferService $mainSubTransfers,
        private readonly InternalSubAccountTransferService $subTransfers,
    ) {
    }

    /**
     * @return array{transaction:Transaction,source:array,destination:array}
     */
    public function transfer(
        User $user,
        string $direction,
        ?int $sourceSubAccountId,
        ?int $destinationSubAccountId,
        int $amountGol,
        string $balanceBucket,
        string $transportIdempotencyKey,
        ?string $description,
        array $expected,
    ): array {
        if ($amountGol <= 0) {
            throw new NajmBaharInternalTransferException(
                'internal_transfer_not_allowed',
                'Internal transfer amount must be positive.',
                409,
            );
        }

        if (! in_array($balanceBucket, ['active', 'dim'], true)) {
            throw new NajmBaharInternalTransferException(
                'internal_transfer_not_allowed',
                'Internal transfer bucket must be Active or Dim.',
                409,
            );
        }

        if (! in_array($direction, ['main_to_sub', 'sub_to_main', 'sub_to_sub'], true)) {
            throw new NajmBaharInternalTransferException(
                'internal_transfer_not_allowed',
                'Unsupported internal transfer direction.',
                409,
            );
        }

        $transportIdempotencyKey = trim($transportIdempotencyKey);
        if ($transportIdempotencyKey === '') {
            throw new \InvalidArgumentException('Internal transfer idempotency key is required.');
        }

        $domainKey = implode('-', [
            'api-v1-internal-transfer',
            'user',
            (int) $user->id,
            hash('sha256', $transportIdempotencyKey),
        ]);

        return DB::transaction(function () use (
            $user,
            $direction,
            $sourceSubAccountId,
            $destinationSubAccountId,
            $amountGol,
            $balanceBucket,
            $description,
            $expected,
            $domainKey,
        ) {
            $main = Account::query()
                ->whereKey($this->accounts->getMainAccountForUser((int) $user->id)?->id)
                ->where('user_id', (int) $user->id)
                ->lockForUpdate()
                ->first();

            if (! $main instanceof Account) {
                throw (new ModelNotFoundException())->setModel(Account::class);
            }

            [$sourceSub, $destinationSub] = $this->resolveDirection(
                $main,
                $direction,
                $sourceSubAccountId,
                $destinationSubAccountId,
            );

            $this->assertCanonicalSubAccount($sourceSub);
            $this->assertCanonicalSubAccount($destinationSub);

            if ($sourceSub instanceof SubAccount
                && $destinationSub instanceof SubAccount
                && (int) $sourceSub->id === (int) $destinationSub->id) {
                throw new NajmBaharInternalTransferException(
                    'internal_transfer_not_allowed',
                    'Source and destination sub-accounts must differ.',
                    409,
                );
            }

            $snapshot = $this->query->forUser($user);
            $sourceProjection = $this->sourceProjection(
                $snapshot,
                $direction,
                $sourceSub,
            );
            $destinationProjection = $this->destinationProjection(
                $snapshot,
                $direction,
                $destinationSub,
            );

            $availableKey = $balanceBucket === 'active'
                ? 'active_available_gol'
                : 'dim_available_gol';

            $this->assertExpected(
                $expected,
                (string) $sourceProjection['account_number'],
                (int) $sourceProjection[$availableKey],
                (string) $destinationProjection['account_number'],
            );

            if ($amountGol > (int) $sourceProjection[$availableKey]) {
                throw new NajmBaharInternalTransferException(
                    'insufficient_available_funds',
                    'Available balance is insufficient for this internal transfer.',
                    409,
                );
            }

            $moneyState = $balanceBucket === 'active' ? 'active' : 'faded';
            $description = $description ?? 'Najm Bahar internal redistribution';
            $meta = [
                'api_v1_operation' => 'internal_transfer',
                'api_v1_actor_id' => (int) $user->id,
                'internal_transfer_direction' => $direction,
            ];

            $transaction = match ($direction) {
                'main_to_sub' => $this->mainSubTransfers->mainToSub(
                    $main,
                    $destinationSub,
                    $amountGol,
                    $moneyState,
                    $description,
                    $domainKey,
                    $meta,
                ),
                'sub_to_main' => $this->mainSubTransfers->subToMain(
                    $sourceSub,
                    $main,
                    $amountGol,
                    $moneyState,
                    $description,
                    $domainKey,
                    $meta,
                ),
                'sub_to_sub' => $this->subTransfers->transfer(
                    $sourceSub,
                    $destinationSub,
                    $amountGol,
                    $moneyState,
                    $description,
                    $domainKey,
                    $meta,
                ),
            };

            $after = $this->query->forUser($user);

            return [
                'transaction' => $transaction->fresh(),
                'source' => $this->sourceProjection($after, $direction, $sourceSub),
                'destination' => $this->destinationProjection($after, $direction, $destinationSub),
            ];
        });
    }

    private function resolveDirection(
        Account $main,
        string $direction,
        ?int $sourceSubAccountId,
        ?int $destinationSubAccountId,
    ): array {
        return match ($direction) {
            'main_to_sub' => [
                null,
                $this->ownedSubAccount($main, $destinationSubAccountId),
            ],
            'sub_to_main' => [
                $this->ownedSubAccount($main, $sourceSubAccountId),
                null,
            ],
            'sub_to_sub' => [
                $this->ownedSubAccount($main, $sourceSubAccountId),
                $this->ownedSubAccount($main, $destinationSubAccountId),
            ],
        };
    }

    private function ownedSubAccount(Account $main, ?int $id): SubAccount
    {
        if ($id === null || $id <= 0) {
            throw (new ModelNotFoundException())->setModel(SubAccount::class);
        }

        $sub = SubAccount::query()
            ->whereKey($id)
            ->where('account_id', (int) $main->id)
            ->where('status', 1)
            ->lockForUpdate()
            ->first();

        if (! $sub instanceof SubAccount) {
            throw (new ModelNotFoundException())->setModel(SubAccount::class);
        }

        return $sub;
    }

    private function assertCanonicalSubAccount(?SubAccount $sub): void
    {
        if (! $sub instanceof SubAccount) {
            return;
        }

        $exists = Account::query()
            ->where('type', 'subaccount')
            ->where('account_number', (string) $sub->sub_account_code)
            ->where('status', 1)
            ->lockForUpdate()
            ->exists();

        if (! $exists) {
            throw (new ModelNotFoundException())->setModel(Account::class);
        }
    }

    private function sourceProjection(array $snapshot, string $direction, ?SubAccount $sourceSub): array
    {
        if ($direction === 'main_to_sub') {
            return (array) $snapshot['main'];
        }

        $projection = collect($snapshot['subaccounts'] ?? [])
            ->firstWhere('sub_account_id', (int) $sourceSub?->id);

        if (! is_array($projection)) {
            throw (new ModelNotFoundException())->setModel(SubAccount::class);
        }

        return $projection;
    }

    private function destinationProjection(array $snapshot, string $direction, ?SubAccount $destinationSub): array
    {
        if ($direction === 'sub_to_main') {
            return (array) $snapshot['main'];
        }

        $projection = collect($snapshot['subaccounts'] ?? [])
            ->firstWhere('sub_account_id', (int) $destinationSub?->id);

        if (! is_array($projection)) {
            throw (new ModelNotFoundException())->setModel(SubAccount::class);
        }

        return $projection;
    }

    private function assertExpected(
        array $expected,
        string $sourceAccountNumber,
        int $sourceAvailableGol,
        string $destinationAccountNumber,
    ): void {
        if (($expected['internal_transfer_contract_version'] ?? null) !== 1
            || ($expected['source_account_number'] ?? null) !== $sourceAccountNumber
            || ($expected['source_available_gol'] ?? null) !== $sourceAvailableGol
            || ($expected['destination_account_number'] ?? null) !== $destinationAccountNumber) {
            throw new NajmBaharInternalTransferException(
                'internal_transfer_terms_changed',
                'Internal transfer terms changed; review current balances and accounts again.',
                409,
            );
        }
    }
}
