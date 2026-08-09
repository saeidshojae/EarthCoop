<?php

namespace App\Modules\NajmBahar\Services;

use App\Modules\Governance\Models\PublicExecutionPaymentInstruction;
use App\Modules\NajmBahar\Models\Account;
use App\Modules\NajmBahar\Models\LedgerEntry;
use App\Modules\NajmBahar\Models\Transaction;
use Illuminate\Support\Facades\DB;
use Throwable;

class PublicExecutionPaymentService
{
    public const MAX_ATTEMPTS = 5;

    public function execute(
        PublicExecutionPaymentInstruction $instruction,
        bool $retryFailed = false
    ): PublicExecutionPaymentInstruction {
        try {
            return DB::transaction(function () use ($instruction, $retryFailed) {
                $lockedInstruction = PublicExecutionPaymentInstruction::whereKey($instruction->id)
                    ->lockForUpdate()
                    ->firstOrFail();

                if ($lockedInstruction->status === 'executed') {
                    return $lockedInstruction;
                }
                if ($lockedInstruction->status === 'dead_letter') {
                    throw new \RuntimeException('Dead-letter public payment requires explicit operator recovery before retry.');
                }
                if ($lockedInstruction->status === 'failed' && ! $retryFailed) {
                    throw new \RuntimeException('Failed public payment requires an explicit retry flag.');
                }
                if (! in_array($lockedInstruction->status, ['approved', 'failed'], true)
                    || ! $lockedInstruction->approved_by
                    || ! $lockedInstruction->approved_at) {
                    throw new \RuntimeException('Public execution payment requires a distinct recorded second approval before execution.');
                }
                if ((int) $lockedInstruction->attempts >= self::MAX_ATTEMPTS) {
                    throw new \RuntimeException('Public execution payment retry budget is exhausted.');
                }

                $lockedInstruction->update([
                    'status' => 'processing',
                    'attempts' => (int) $lockedInstruction->attempts + 1,
                    'last_attempt_at' => now(),
                    'failure_reason' => null,
                    'failed_at' => null,
                ]);

                $amount = (int) $lockedInstruction->amount_gol;
                if ($amount <= 0) {
                    throw new \RuntimeException('Public execution payment instruction has an invalid amount.');
                }

                $accountIds = [
                    (int) $lockedInstruction->execution_account_id,
                    (int) $lockedInstruction->payee_account_id,
                ];
                sort($accountIds, SORT_NUMERIC);
                $accounts = [];
                foreach ($accountIds as $accountId) {
                    $accounts[$accountId] = Account::whereKey($accountId)->lockForUpdate()->firstOrFail();
                }

                $source = $accounts[(int) $lockedInstruction->execution_account_id];
                $payee = $accounts[(int) $lockedInstruction->payee_account_id];
                if ($source->type !== 'legal_entity') {
                    throw new \RuntimeException('Public execution payment source must be the legal-entity execution account.');
                }
                if ($payee->type === 'subaccount') {
                    throw new \RuntimeException('Public execution payment payee must use a canonical main or legal-entity account.');
                }
                if ((int) ($source->balance_active ?? 0) < $amount) {
                    throw new \RuntimeException('Execution account has insufficient Active Bahar for payment.');
                }

                $idempotencyKey = 'public-execution-payment:instruction:' . $lockedInstruction->id;
                $existingTransaction = Transaction::where('metadata->idempotency_key', $idempotencyKey)
                    ->lockForUpdate()
                    ->first();
                if ($existingTransaction) {
                    $metadata = (array) ($lockedInstruction->metadata ?? []);
                    $metadata['payment_transaction_id'] = (int) $existingTransaction->id;
                    $metadata['operator_attention_required'] = false;
                    $lockedInstruction->update([
                        'status' => 'executed',
                        'executed_at' => $lockedInstruction->executed_at ?: now(),
                        'failure_reason' => null,
                        'failed_at' => null,
                        'metadata' => $metadata,
                    ]);
                    return $lockedInstruction->fresh();
                }

                $source->balance_active = (int) ($source->balance_active ?? 0) - $amount;
                $source->balance = (int) ($source->balance_active ?? 0)
                    + (int) ($source->balance_faded ?? 0)
                    + (int) ($source->committed_dim ?? 0);
                $source->save();

                $payee->balance_active = (int) ($payee->balance_active ?? 0) + $amount;
                $payee->balance = (int) ($payee->balance_active ?? 0)
                    + (int) ($payee->balance_faded ?? 0)
                    + (int) ($payee->committed_dim ?? 0);
                $payee->save();

                $eventMeta = [
                    'type' => 'public_execution_payment',
                    'monetary_event' => 'money_transferred',
                    'money_state' => 'active',
                    'idempotency_key' => $idempotencyKey,
                    'public_execution_payment_instruction_id' => (int) $lockedInstruction->id,
                    'public_contribution_plan_id' => (int) $lockedInstruction->plan_id,
                    'execution_authorization_id' => (int) $lockedInstruction->authorization_id,
                    'execution_account_id' => (int) $source->id,
                    'payee_account_id' => (int) $payee->id,
                    'approved_by' => (int) $lockedInstruction->approved_by,
                    'amount_gol' => $amount,
                    'system_operation' => true,
                ];

                $transaction = Transaction::create([
                    'from_account_id' => $source->id,
                    'to_account_id' => $payee->id,
                    'amount' => $amount,
                    'type' => 'immediate',
                    'status' => 'completed',
                    'metadata' => $eventMeta,
                    'description' => $lockedInstruction->purpose,
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
                    'account_id' => $payee->id,
                    'amount' => $amount,
                    'entry_type' => 'credit',
                    'meta' => array_merge($eventMeta, ['balance_bucket' => 'active']),
                ]);

                $metadata = (array) ($lockedInstruction->metadata ?? []);
                $metadata['payment_transaction_id'] = (int) $transaction->id;
                $metadata['operator_attention_required'] = false;
                $lockedInstruction->update([
                    'status' => 'executed',
                    'executed_at' => now(),
                    'failure_reason' => null,
                    'failed_at' => null,
                    'metadata' => $metadata,
                ]);

                return $lockedInstruction->fresh();
            }, 3);
        } catch (Throwable $e) {
            DB::transaction(function () use ($instruction, $e) {
                $failedInstruction = PublicExecutionPaymentInstruction::whereKey($instruction->id)
                    ->lockForUpdate()
                    ->first();
                if (! $failedInstruction
                    || in_array($failedInstruction->status, ['executed', 'cancelled', 'dead_letter'], true)
                    || ! in_array($failedInstruction->status, ['approved', 'failed', 'processing'], true)) {
                    return;
                }

                $attempts = min(self::MAX_ATTEMPTS, (int) $failedInstruction->attempts + 1);
                $deadLetter = $attempts >= self::MAX_ATTEMPTS;
                $metadata = (array) ($failedInstruction->metadata ?? []);
                $metadata['operator_attention_required'] = true;
                $metadata['last_payment_failure_at'] = now()->toIso8601String();

                $failedInstruction->update([
                    'status' => $deadLetter ? 'dead_letter' : 'failed',
                    'attempts' => $attempts,
                    'last_attempt_at' => now(),
                    'failed_at' => now(),
                    'failure_reason' => mb_substr($e->getMessage(), 0, 2000),
                    'metadata' => $metadata,
                ]);
            }, 3);

            throw $e;
        }
    }

    public function recoverDeadLetter(PublicExecutionPaymentInstruction $instruction): PublicExecutionPaymentInstruction
    {
        return DB::transaction(function () use ($instruction) {
            $lockedInstruction = PublicExecutionPaymentInstruction::whereKey($instruction->id)
                ->lockForUpdate()
                ->firstOrFail();
            if ($lockedInstruction->status !== 'dead_letter') {
                throw new \RuntimeException('Only a dead-letter public payment can be recovered.');
            }

            $metadata = (array) ($lockedInstruction->metadata ?? []);
            $metadata['dead_letter_recovered_at'] = now()->toIso8601String();
            $metadata['operator_attention_required'] = true;

            $lockedInstruction->update([
                'status' => 'failed',
                'attempts' => 0,
                'failure_reason' => null,
                'failed_at' => null,
                'metadata' => $metadata,
            ]);

            return $lockedInstruction->fresh();
        }, 3);
    }
}
