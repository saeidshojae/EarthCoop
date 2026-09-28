<?php

namespace App\Modules\NajmBahar\Services\Api;

use App\Models\User;
use App\Modules\NajmBahar\Models\ScheduledTransaction;
use App\Modules\NajmBahar\Models\SubAccount;

class NajmBaharScheduledOperationQueryService
{
    public function __construct(
        private readonly NajmBaharAccountQueryService $accounts,
    ) {
    }

    /**
     * Return the authenticated member's existing scheduled sub-account operations.
     *
     * Ownership is derived exclusively from the persisted source sub-account and
     * its parent main account. Payload actor metadata is intentionally ignored.
     *
     * @return array<int, array<string, mixed>>
     */
    public function forUser(User $user, int $limit = 100): array
    {
        $main = $this->accounts->mainFor($user);

        $ownedSourceIds = SubAccount::query()
            ->where('account_id', (int) $main->id)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();

        if ($ownedSourceIds === []) {
            return [];
        }

        $query = ScheduledTransaction::query()
            ->where('payload->type', 'subaccount_transfer')
            ->where(function ($query) use ($ownedSourceIds) {
                foreach ($ownedSourceIds as $sourceId) {
                    $query->orWhere('payload->from_sub_account_id', $sourceId);
                }
            })
            ->orderByDesc('execute_at')
            ->orderByDesc('id')
            ->limit(max(1, min($limit, 100)));

        $scheduled = $query->get();

        $referencedIds = $scheduled
            ->flatMap(function (ScheduledTransaction $operation): array {
                $payload = (array) ($operation->payload ?? []);

                return [
                    (int) ($payload['from_sub_account_id'] ?? 0),
                    (int) ($payload['to_sub_account_id'] ?? 0),
                ];
            })
            ->filter(fn (int $id) => $id > 0)
            ->unique()
            ->values();

        $subAccounts = SubAccount::query()
            ->whereIn('id', $referencedIds->all())
            ->get()
            ->keyBy(fn (SubAccount $subAccount) => (int) $subAccount->id);

        return $scheduled
            ->map(function (ScheduledTransaction $operation) use ($subAccounts, $main): ?array {
                $payload = (array) ($operation->payload ?? []);
                $sourceId = (int) ($payload['from_sub_account_id'] ?? 0);
                $destinationId = (int) ($payload['to_sub_account_id'] ?? 0);
                $amountGol = (int) ($payload['amount'] ?? 0);
                $moneyState = (string) ($payload['money_state'] ?? '');

                $source = $subAccounts->get($sourceId);
                $destination = $subAccounts->get($destinationId);

                if (! $source instanceof SubAccount
                    || ! $destination instanceof SubAccount
                    || (int) $source->account_id !== (int) $main->id
                    || $sourceId <= 0
                    || $destinationId <= 0
                    || $amountGol <= 0
                    || ! in_array($moneyState, ['active', 'faded'], true)
                ) {
                    return null;
                }

                return [
                    'id' => (int) $operation->id,
                    'transaction_id' => $operation->transaction_id === null
                        ? null
                        : (int) $operation->transaction_id,
                    'status' => (string) $operation->status,
                    'execute_at' => $operation->execute_at?->toISOString(),
                    'amount_gol' => $amountGol,
                    'balance_bucket' => $moneyState === 'active' ? 'active' : 'dim',
                    'description' => array_key_exists('description', $payload)
                        ? ($payload['description'] === null ? null : (string) $payload['description'])
                        : null,
                    'source_sub_account' => [
                        'id' => (int) $source->id,
                        'code' => (string) $source->sub_account_code,
                    ],
                    'destination_sub_account' => [
                        'id' => (int) $destination->id,
                        'code' => (string) $destination->sub_account_code,
                    ],
                ];
            })
            ->filter()
            ->values()
            ->all();
    }
}
