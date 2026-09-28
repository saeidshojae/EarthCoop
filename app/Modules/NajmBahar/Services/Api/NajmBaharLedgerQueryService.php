<?php

namespace App\Modules\NajmBahar\Services\Api;

use App\Http\Support\Api\V1\CursorOptions;
use App\Models\User;
use App\Modules\NajmBahar\Models\Account;
use App\Modules\NajmBahar\Models\LedgerEntry;
use App\Modules\NajmBahar\Models\SubAccount;
use App\Modules\NajmBahar\Models\Transaction;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Pagination\Cursor;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class NajmBaharLedgerQueryService
{
    public function history(User $user, CursorOptions $page, array $filters): array
    {
        $ownedAccountIds = $this->ownedAccountIds($user);

        $scopedAccountId = null;
        if (array_key_exists('account_id', $filters)) {
            $scopedAccountId = $this->positiveInteger($filters['account_id'], 'filter.account_id');
            if (! $ownedAccountIds->contains($scopedAccountId)) {
                throw (new ModelNotFoundException())->setModel(Account::class);
            }
        }

        $scopeIds = $scopedAccountId === null
            ? $ownedAccountIds->all()
            : [$scopedAccountId];

        $query = Transaction::query()
            ->with([
                'fromAccount:id,account_number,name,type,user_id',
                'toAccount:id,account_number,name,type,user_id',
            ])
            ->where(function ($builder) use ($scopeIds) {
                $builder->whereIn('from_account_id', $scopeIds)
                    ->orWhereIn('to_account_id', $scopeIds);
            });

        if (array_key_exists('type', $filters)) {
            $query->where('type', $this->scalarFilter($filters['type'], 'filter.type'));
        }

        if (array_key_exists('status', $filters)) {
            $query->where('status', $this->scalarFilter($filters['status'], 'filter.status'));
        }

        $cursor = is_string($page->cursor) && $page->cursor !== ''
            ? Cursor::fromEncoded($page->cursor)
            : null;

        $paginator = $query
            ->orderByDesc('id')
            ->cursorPaginate($page->limit, ['*'], 'cursor', $cursor);

        $transactions = collect($paginator->items());
        $transactionIds = $transactions->pluck('id')->map(fn ($id) => (int) $id)->all();

        $ownedEntries = $transactionIds === []
            ? collect()
            : LedgerEntry::query()
                ->whereIn('transaction_id', $transactionIds)
                ->whereIn('account_id', $ownedAccountIds->all())
                ->orderBy('id')
                ->get()
                ->groupBy('transaction_id');

        $items = $transactions
            ->map(fn (Transaction $transaction) => $this->projectTransaction(
                $transaction,
                $ownedAccountIds->all(),
                $ownedEntries->get($transaction->id, collect()),
            ))
            ->values()
            ->all();

        return [
            'items' => $items,
            'pagination' => [
                'next_cursor' => $paginator->nextCursor()?->encode(),
                'has_more' => $paginator->hasMorePages(),
            ],
        ];
    }

    public function transactionFor(User $user, Transaction $transaction): array
    {
        $ownedAccountIds = $this->ownedAccountIds($user);
        $ownedIds = $ownedAccountIds->all();

        if (! $ownedAccountIds->contains((int) $transaction->from_account_id)
            && ! $ownedAccountIds->contains((int) $transaction->to_account_id)) {
            throw (new ModelNotFoundException())->setModel(Transaction::class);
        }

        $transaction->loadMissing([
            'fromAccount:id,account_number,name,type,user_id',
            'toAccount:id,account_number,name,type,user_id',
        ]);

        $entries = LedgerEntry::query()
            ->where('transaction_id', (int) $transaction->id)
            ->whereIn('account_id', $ownedIds)
            ->orderBy('id')
            ->get();

        return $this->projectTransaction($transaction, $ownedIds, $entries);
    }

    public function ownedAccountIds(User $user): Collection
    {
        $mainIds = Account::query()
            ->where('user_id', (int) $user->id)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->values();

        if ($mainIds->isEmpty()) {
            return $mainIds;
        }

        $subAccountCodes = SubAccount::query()
            ->whereIn('account_id', $mainIds->all())
            ->pluck('sub_account_code')
            ->filter()
            ->values();

        $mirrorIds = $subAccountCodes->isEmpty()
            ? collect()
            : Account::query()
                ->where('type', 'subaccount')
                ->whereIn('account_number', $subAccountCodes->all())
                ->pluck('id')
                ->map(fn ($id) => (int) $id)
                ->values();

        return $mainIds
            ->merge($mirrorIds)
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();
    }

    private function projectTransaction(Transaction $transaction, array $ownedAccountIds, Collection $entries): array
    {
        $ownedIds = array_fill_keys(array_map('intval', $ownedAccountIds), true);
        $hasDebit = $entries->contains(fn (LedgerEntry $entry) => $entry->entry_type === 'debit');
        $hasCredit = $entries->contains(fn (LedgerEntry $entry) => $entry->entry_type === 'credit');

        $direction = $hasDebit && $hasCredit
            ? 'internal'
            : ($hasDebit ? 'outgoing' : 'incoming');

        if ($entries->isEmpty()) {
            $fromOwned = isset($ownedIds[(int) $transaction->from_account_id]);
            $toOwned = isset($ownedIds[(int) $transaction->to_account_id]);
            $direction = $fromOwned && $toOwned
                ? 'internal'
                : ($fromOwned ? 'outgoing' : 'incoming');
        }

        $entryMeta = $entries->first()?->meta;
        $metadata = is_array($entryMeta)
            ? $entryMeta
            : (is_array($transaction->metadata) ? $transaction->metadata : []);

        $rawBucket = (string) ($metadata['balance_type'] ?? $metadata['money_state'] ?? 'balance');
        $balanceBucket = match ($rawBucket) {
            'active' => 'active',
            'faded' => 'dim',
            default => 'legacy',
        };

        $counterparty = match ($direction) {
            'incoming' => $transaction->fromAccount,
            'outgoing' => $transaction->toAccount,
            default => null,
        };

        return [
            'id' => (int) $transaction->id,
            'tracking_number' => (string) $transaction->tracking_number,
            'type' => (string) $transaction->type,
            'status' => (string) $transaction->status,
            'amount_gol' => (int) $transaction->amount,
            'balance_bucket' => $balanceBucket,
            'direction' => $direction,
            'counterparty' => $counterparty === null ? null : [
                'account_number' => (string) $counterparty->account_number,
                'name' => (string) $counterparty->name,
                'type' => (string) $counterparty->type,
            ],
            'description' => $transaction->description === null ? null : (string) $transaction->description,
            'created_at' => $transaction->created_at?->toISOString(),
        ];
    }

    private function scalarFilter(mixed $value, string $field): string
    {
        if (! is_string($value) && ! is_int($value)) {
            throw ValidationException::withMessages([
                $field => ['The filter value must be a scalar string.'],
            ]);
        }

        $value = trim((string) $value);
        if ($value === '') {
            throw ValidationException::withMessages([
                $field => ['The filter value must not be empty.'],
            ]);
        }

        return $value;
    }

    private function positiveInteger(mixed $value, string $field): int
    {
        if (is_int($value) && $value > 0) {
            return $value;
        }

        if (is_string($value) && $value !== '' && ctype_digit($value) && (int) $value > 0) {
            return (int) $value;
        }

        throw ValidationException::withMessages([
            $field => ['The value must be a positive integer.'],
        ]);
    }
}
