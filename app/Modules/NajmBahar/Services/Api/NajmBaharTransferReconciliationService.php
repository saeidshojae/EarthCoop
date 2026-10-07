<?php

namespace App\Modules\NajmBahar\Services\Api;

use App\Models\User;
use App\Modules\NajmBahar\Models\Transaction;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;

class NajmBaharTransferReconciliationService
{
    public function __construct(
        private readonly NajmBaharLedgerQueryService $ledger,
    ) {
    }

    public function findCompleted(User $user, string $idempotencyKey): array
    {
        $record = DB::table('api_v1_idempotency_keys')
            ->where('actor_key', 'user:'.$user->getAuthIdentifier())
            ->where('scope', 'najm-bahar.transfers.store')
            ->where('idempotency_key', $idempotencyKey)
            ->where('state', 'completed')
            ->first();

        if (! $record
            || ($record->expires_at !== null && now()->greaterThan($record->expires_at))
            || (int) ($record->response_status ?? 0) < 200
            || (int) ($record->response_status ?? 0) >= 300
        ) {
            throw (new ModelNotFoundException())->setModel(Transaction::class);
        }

        try {
            $body = json_decode((string) $record->response_body, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            throw (new ModelNotFoundException())->setModel(Transaction::class);
        }

        $transactionId = $body['data']['transaction']['id'] ?? null;
        if (! is_int($transactionId) || $transactionId <= 0) {
            throw (new ModelNotFoundException())->setModel(Transaction::class);
        }

        $transaction = Transaction::query()->find($transactionId);
        if (! $transaction instanceof Transaction) {
            throw (new ModelNotFoundException())->setModel(Transaction::class);
        }

        return $this->ledger->transactionFor($user, $transaction);
    }
}
