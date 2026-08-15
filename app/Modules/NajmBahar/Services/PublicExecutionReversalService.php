<?php

namespace App\Modules\NajmBahar\Services;

use App\Modules\Governance\Models\PublicExecutionPaymentInstruction;
use App\Modules\Governance\Models\PublicExecutionReversalRequest;
use App\Modules\NajmBahar\Models\Account;
use App\Modules\NajmBahar\Models\LedgerEntry;
use App\Modules\NajmBahar\Models\Transaction;
use Illuminate\Support\Facades\DB;
use Throwable;

class PublicExecutionReversalService
{
    public const MAX_ATTEMPTS = 5;

    public function execute(
        PublicExecutionReversalRequest $request,
        bool $retryFailed = false
    ): PublicExecutionReversalRequest {
        try {
            return DB::transaction(function () use ($request, $retryFailed) {
                $lockedRequest = PublicExecutionReversalRequest::whereKey($request->id)
                    ->lockForUpdate()
                    ->firstOrFail();

                if ($lockedRequest->status === 'executed') {
                    return $lockedRequest;
                }
                if ($lockedRequest->status === 'dead_letter') {
                    throw new \RuntimeException('Dead-letter public reversal requires explicit operator recovery before retry.');
                }
                if ($lockedRequest->status === 'failed' && ! $retryFailed) {
                    throw new \RuntimeException('Failed public reversal requires an explicit retry flag.');
                }
                if (! in_array($lockedRequest->status, ['approved', 'failed'], true)
                    || ! $lockedRequest->approved_by
                    || ! $lockedRequest->approved_at) {
                    throw new \RuntimeException('Public payment reversal requires a distinct recorded second approval before execution.');
                }
                if ((int) $lockedRequest->attempts >= self::MAX_ATTEMPTS) {
                    throw new \RuntimeException('Public payment reversal retry budget is exhausted.');
                }

                $payment = PublicExecutionPaymentInstruction::whereKey($lockedRequest->payment_instruction_id)
                    ->lockForUpdate()
                    ->firstOrFail();
                if ($payment->status !== 'executed' || ! $payment->executed_at) {
                    throw new \RuntimeException('Original public payment is not in an executed state.');
                }

                $lockedRequest->update([
                    'status' => 'processing',
                    'attempts' => (int) $lockedRequest->attempts + 1,
                    'last_attempt_at' => now(),
                    'last_error' => null,
                    'failed_at' => null,
                ]);

                $amount = (int) $lockedRequest->amount_gol;
                if ($amount <= 0 || $amount > (int) $payment->amount_gol) {
                    throw new \RuntimeException('Public payment reversal has an invalid amount.');
                }

                $sourceId = (int) $payment->payee_account_id;
                $destinationId = (int) $payment->execution_account_id;
                $accountIds = [$sourceId, $destinationId];
                sort($accountIds, SORT_NUMERIC);
                $accounts = [];
                foreach ($accountIds as $accountId) {
                    $accounts[$accountId] = Account::whereKey($accountId)->lockForUpdate()->firstOrFail();
                }

                $source = $accounts[$sourceId];
                $destination = $accounts[$destinationId];
                if ($source->type === 'subaccount') {
                    throw new \RuntimeException('Public reversal source must be the canonical original payee account.');
                }
                if ($destination->type !== 'legal_entity') {
                    throw new \RuntimeException('Public reversal destination must be the legal-entity execution account.');
                }
                if ((int) ($source->balance_active ?? 0) < $amount) {
                    throw new \RuntimeException('Original payee has insufficient Active Bahar for reversal.');
                }

                $idempotencyKey = 'public-execution-reversal:request:' . $lockedRequest->id;
                $existingTransaction = Transaction::where('metadata->idempotency_key', $idempotencyKey)
                    ->lockForUpdate()
                    ->first();
                if ($existingTransaction) {
                    $metadata = (array) ($lockedRequest->metadata ?? []);
                    $metadata['reversal_transaction_id'] = (int) $existingTransaction->id;
                    $metadata['operator_attention_required'] = false;
                    $lockedRequest->update([
                        'status' => 'executed',
                        'executed_at' => $lockedRequest->executed_at ?: now(),
                        'last_error' => null,
                        'failed_at' => null,
                        'metadata' => $metadata,
                    ]);
                    return $lockedRequest->fresh();
                }

                $source->balance_active = (int) ($source->balance_active ?? 0) - $amount;
                $source->balance = (int) $source->balance_active
                    + (int) ($source->balance_faded ?? 0)
                    + (int) ($source->committed_dim ?? 0);
                $source->save();

                $destination->balance_active = (int) ($destination->balance_active ?? 0) + $amount;
                $destination->balance = (int) $destination->balance_active
                    + (int) ($destination->balance_faded ?? 0)
                    + (int) ($destination->committed_dim ?? 0);
                $destination->save();

                $eventMeta = [
                    'type' => 'public_execution_payment_reversal',
                    'monetary_event' => 'money_transferred',
                    'money_state' => 'active',
                    'idempotency_key' => $idempotencyKey,
                    'public_execution_reversal_request_id' => (int) $lockedRequest->id,
                    'original_payment_instruction_id' => (int) $payment->id,
                    'original_payment_transaction_id' => (int) (($payment->metadata ?? [])['payment_transaction_id'] ?? 0),
                    'execution_account_id' => $destinationId,
                    'original_payee_account_id' => $sourceId,
                    'approved_by' => (int) $lockedRequest->approved_by,
                    'amount_gol' => $amount,
                    'system_operation' => true,
                ];

                $transaction = Transaction::create([
                    'from_account_id' => $source->id,
                    'to_account_id' => $destination->id,
                    'amount' => $amount,
                    'type' => 'immediate',
                    'status' => 'completed',
                    'metadata' => $eventMeta,
                    'description' => 'Reversal: ' . $lockedRequest->reason,
                ]);

                LedgerEntry::create([
                    'transaction_id' => $transaction->id,
                    'account_id' => $source->id,
                    'amount' => -$amount,
                    'entry_type' => 'debit',
                    'meta' => array_merge($eventMeta, ['balance_bucket' => 'active']),
                ]);
                LedgerEntry::create([
                    'transaction_id' => $transaction->id,
                    'account_id' => $destination->id,
                    'amount' => $amount,
                    'entry_type' => 'credit',
                    'meta' => array_merge($eventMeta, ['balance_bucket' => 'active']),
                ]);

                $metadata = (array) ($lockedRequest->metadata ?? []);
                $metadata['reversal_transaction_id'] = (int) $transaction->id;
                $metadata['operator_attention_required'] = false;
                $lockedRequest->update([
                    'status' => 'executed',
                    'executed_at' => now(),
                    'last_error' => null,
                    'failed_at' => null,
                    'metadata' => $metadata,
                ]);

                return $lockedRequest->fresh();
            }, 3);
        } catch (Throwable $e) {
            DB::transaction(function () use ($request, $e) {
                $failed = PublicExecutionReversalRequest::whereKey($request->id)
                    ->lockForUpdate()
                    ->first();
                if (! $failed
                    || in_array($failed->status, ['executed', 'cancelled', 'dead_letter'], true)
                    || ! in_array($failed->status, ['approved', 'failed', 'processing'], true)) {
                    return;
                }

                $attempts = min(self::MAX_ATTEMPTS, (int) $failed->attempts + 1);
                $deadLetter = $attempts >= self::MAX_ATTEMPTS;
                $metadata = (array) ($failed->metadata ?? []);
                $metadata['operator_attention_required'] = true;
                $metadata['last_reversal_failure_at'] = now()->toIso8601String();

                $failed->update([
                    'status' => $deadLetter ? 'dead_letter' : 'failed',
                    'attempts' => $attempts,
                    'last_attempt_at' => now(),
                    'failed_at' => now(),
                    'last_error' => mb_substr($e->getMessage(), 0, 2000),
                    'metadata' => $metadata,
                ]);
            }, 3);

            throw $e;
        }
    }

    public function recoverDeadLetter(PublicExecutionReversalRequest $request): PublicExecutionReversalRequest
    {
        return DB::transaction(function () use ($request) {
            $locked = PublicExecutionReversalRequest::whereKey($request->id)
                ->lockForUpdate()
                ->firstOrFail();
            if ($locked->status !== 'dead_letter') {
                throw new \RuntimeException('Only a dead-letter public reversal can be recovered.');
            }

            $metadata = (array) ($locked->metadata ?? []);
            $metadata['dead_letter_recovered_at'] = now()->toIso8601String();
            $metadata['operator_attention_required'] = true;

            $locked->update([
                'status' => 'failed',
                'attempts' => 0,
                'last_error' => null,
                'failed_at' => null,
                'metadata' => $metadata,
            ]);

            return $locked->fresh();
        }, 3);
    }
}
