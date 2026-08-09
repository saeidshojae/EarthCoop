<?php

namespace App\Modules\NajmBahar\Services;

use App\Modules\Governance\Models\PublicExecutionPaymentInstruction;
use App\Modules\NajmBahar\Models\Account;
use App\Modules\NajmBahar\Models\LedgerEntry;
use App\Modules\NajmBahar\Models\Transaction;
use Illuminate\Support\Facades\DB;

class PublicExecutionPaymentService
{
    public function execute(PublicExecutionPaymentInstruction $instruction): PublicExecutionPaymentInstruction
    {
        return DB::transaction(function () use ($instruction) {
            $lockedInstruction = PublicExecutionPaymentInstruction::whereKey($instruction->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedInstruction->status === 'executed') {
                return $lockedInstruction;
            }
            if ($lockedInstruction->status !== 'approved'
                || ! $lockedInstruction->approved_by
                || ! $lockedInstruction->approved_at) {
                throw new \RuntimeException('Public execution payment requires a distinct recorded second approval before execution.');
            }

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
                $lockedInstruction->update([
                    'status' => 'executed',
                    'executed_at' => $lockedInstruction->executed_at ?: now(),
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
            $lockedInstruction->update([
                'status' => 'executed',
                'executed_at' => now(),
                'metadata' => $metadata,
            ]);

            return $lockedInstruction->fresh();
        }, 3);
    }
}
