<?php

namespace App\Modules\NajmBahar\Services;

use App\Modules\NajmBahar\Models\Transaction;
use Illuminate\Database\QueryException;

class FinancialIdempotencyReplayService
{
    public function find(string $idempotencyKey): ?Transaction
    {
        return Transaction::where('idempotency_key', $idempotencyKey)->first()
            ?? Transaction::where('metadata->idempotency_key', $idempotencyKey)->first();
    }

    public function replayAfterUniqueConflict(string $idempotencyKey, QueryException $exception): Transaction
    {
        if (! $this->isUniqueConstraintViolation($exception)) {
            throw $exception;
        }

        $existing = $this->find($idempotencyKey);
        if (! $existing) {
            // Never hide an unexpected database failure merely because its SQL
            // state resembles a uniqueness conflict. A winning transaction must
            // be visible after the losing transaction has rolled back.
            throw $exception;
        }

        return $existing;
    }

    public function isUniqueConstraintViolation(QueryException $exception): bool
    {
        return in_array((string) $exception->getCode(), ['23000', '23505'], true)
            || in_array((int) ($exception->errorInfo[1] ?? 0), [1062, 19, 1555, 2067], true);
    }
}
