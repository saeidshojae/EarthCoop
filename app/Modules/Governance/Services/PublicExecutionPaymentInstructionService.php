<?php

namespace App\Modules\Governance\Services;

use App\Models\GroupUser;
use App\Models\User;
use App\Modules\Governance\Models\PublicContributionPlan;
use App\Modules\Governance\Models\PublicExecutionAuthorization;
use App\Modules\Governance\Models\PublicExecutionPaymentInstruction;
use App\Modules\NajmBahar\Models\Account;
use Illuminate\Support\Facades\DB;

class PublicExecutionPaymentInstructionService
{
    public function create(
        PublicContributionPlan $plan,
        Account $payeeAccount,
        int $amountGol,
        string $purpose,
        User $actor,
        string $idempotencyKey,
        array $evidence = []
    ): PublicExecutionPaymentInstruction {
        if ($amountGol <= 0) {
            throw new \InvalidArgumentException('Public execution payment amount must be positive integer Gol.');
        }
        if (trim($purpose) === '') {
            throw new \InvalidArgumentException('Public execution payment purpose is required.');
        }
        if (trim($idempotencyKey) === '') {
            throw new \InvalidArgumentException('Public execution payment idempotency key is required.');
        }

        return DB::transaction(function () use ($plan, $payeeAccount, $amountGol, $purpose, $actor, $idempotencyKey, $evidence) {
            $existing = PublicExecutionPaymentInstruction::where('idempotency_key', $idempotencyKey)
                ->lockForUpdate()
                ->first();
            if ($existing) {
                return $existing;
            }

            $lockedPlan = PublicContributionPlan::whereKey($plan->id)->lockForUpdate()->firstOrFail();
            if ($lockedPlan->status !== 'executed') {
                throw new \RuntimeException('Public capital must be settled into the execution account before payment instructions can be created.');
            }

            $membership = GroupUser::where('group_id', $lockedPlan->group_id)
                ->where('user_id', $actor->id)
                ->where('status', 1)
                ->first();
            if (! $membership || ! in_array((int) $membership->role, [2, 3], true)) {
                throw new \RuntimeException('Only an active group manager or inspector may create a public execution payment instruction.');
            }

            $authorization = PublicExecutionAuthorization::where('plan_id', $lockedPlan->id)
                ->lockForUpdate()
                ->firstOrFail();
            if ($authorization->status !== 'consumed') {
                throw new \RuntimeException('The public execution authorization must be consumed before contractor payment can be instructed.');
            }

            $executionAccountId = (int) (($lockedPlan->metadata ?? [])['execution_account_id'] ?? 0);
            if ($executionAccountId <= 0) {
                throw new \RuntimeException('Executed public plan has no canonical execution account.');
            }

            $executionAccount = Account::whereKey($executionAccountId)->lockForUpdate()->firstOrFail();
            $lockedPayee = Account::whereKey($payeeAccount->id)->lockForUpdate()->firstOrFail();

            if ((int) $executionAccount->id === (int) $lockedPayee->id) {
                throw new \RuntimeException('Public execution payee must be distinct from the execution account.');
            }
            if ($lockedPayee->type === 'subaccount') {
                throw new \RuntimeException('Public execution payee must use a canonical main or legal-entity account.');
            }

            $reservedByOpenInstructions = (int) PublicExecutionPaymentInstruction::where('plan_id', $lockedPlan->id)
                ->whereIn('status', ['pending_approval', 'approved'])
                ->lockForUpdate()
                ->sum('amount_gol');
            $availableForInstruction = (int) ($executionAccount->balance_active ?? 0) - $reservedByOpenInstructions;
            if ($availableForInstruction < $amountGol) {
                throw new \RuntimeException('Execution account has insufficient unreserved Active Bahar for the payment instruction.');
            }

            return PublicExecutionPaymentInstruction::create([
                'plan_id' => $lockedPlan->id,
                'authorization_id' => $authorization->id,
                'execution_account_id' => $executionAccount->id,
                'payee_account_id' => $lockedPayee->id,
                'created_by' => $actor->id,
                'amount_gol' => $amountGol,
                'status' => 'pending_approval',
                'idempotency_key' => $idempotencyKey,
                'purpose' => trim($purpose),
                'evidence' => $evidence,
                'metadata' => [
                    'money_state' => 'active',
                    'automatic_payment' => false,
                    'governance_instruction_only' => true,
                    'payment_requires_najm_bahar_execution' => true,
                    'second_approval_required' => true,
                    'resolution_id' => (int) $lockedPlan->resolution_id,
                    'group_id' => (int) $lockedPlan->group_id,
                ],
            ]);
        }, 3);
    }
}
