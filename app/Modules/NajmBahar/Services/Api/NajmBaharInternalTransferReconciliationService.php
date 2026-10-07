<?php

namespace App\Modules\NajmBahar\Services\Api;

use App\Models\User;
use App\Modules\NajmBahar\Models\Transaction;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;

class NajmBaharInternalTransferReconciliationService
{
    public function __construct(
        private readonly NajmBaharLedgerQueryService $ledger,
    ) {
    }

    /**
     * Return the original successful API receipt for this actor/key.
     *
     * @return array{transaction:array,source:array,destination:array}
     */
    public function findCompleted(User $user, string $idempotencyKey): array
    {
        $record = DB::table('api_v1_idempotency_keys')
            ->where('actor_key', 'user:'.$user->getAuthIdentifier())
            ->where('scope', 'api.v1.najm-bahar.internal-transfers.store')
            ->where('idempotency_key', $idempotencyKey)
            ->where('state', 'completed')
            ->first();

        if (! $record
            || ($record->expires_at !== null && now()->greaterThan($record->expires_at))
            || (int) ($record->response_status ?? 0) < 200
            || (int) ($record->response_status ?? 0) >= 300
        ) {
            $this->notFound();
        }

        try {
            $body = json_decode((string) $record->response_body, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            $this->notFound();
        }

        $receipt = $body['data'] ?? null;
        $transactionId = is_array($receipt) ? ($receipt['transaction']['id'] ?? null) : null;

        if (! is_array($receipt)
            || ! is_array($receipt['transaction'] ?? null)
            || ! is_array($receipt['source'] ?? null)
            || ! is_array($receipt['destination'] ?? null)
            || ! is_int($transactionId)
            || $transactionId <= 0
            || ($receipt['transaction']['status'] ?? null) !== 'completed'
            || ($receipt['transaction']['direction'] ?? null) !== 'internal'
        ) {
            $this->notFound();
        }

        $transaction = Transaction::query()->find($transactionId);
        if (! $transaction instanceof Transaction) {
            $this->notFound();
        }

        // Reuse the canonical ownership boundary. This throws not-found if the
        // transaction no longer belongs to the authenticated economic owner.
        $projected = $this->ledger->transactionFor($user, $transaction);
        if ((int) ($projected['id'] ?? 0) !== $transactionId
            || ($projected['status'] ?? null) !== 'completed'
            || ($projected['direction'] ?? null) !== 'internal') {
            $this->notFound();
        }

        return [
            'transaction' => $receipt['transaction'],
            'source' => $receipt['source'],
            'destination' => $receipt['destination'],
        ];
    }

    private function notFound(): never
    {
        throw (new ModelNotFoundException())->setModel(Transaction::class);
    }
}
