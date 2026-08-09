<?php

namespace App\Modules\Governance\Services;

use App\Models\GroupUser;
use App\Models\User;
use App\Modules\Governance\Models\PublicExecutionPaymentInstruction;
use App\Modules\Governance\Models\PublicExecutionReversalRequest;
use Illuminate\Support\Facades\DB;

class PublicExecutionReversalRequestService
{
    public function create(
        PublicExecutionPaymentInstruction $payment,
        int $amountGol,
        string $reason,
        User $actor,
        string $idempotencyKey,
        array $evidence = []
    ): PublicExecutionReversalRequest {
        if ($amountGol <= 0) {
            throw new \InvalidArgumentException('Reversal amount must be positive integer Gol.');
        }
        if (trim($reason) === '' || trim($idempotencyKey) === '') {
            throw new \InvalidArgumentException('Reversal reason and idempotency key are required.');
        }

        return DB::transaction(function () use ($payment, $amountGol, $reason, $actor, $idempotencyKey, $evidence) {
            $existing = PublicExecutionReversalRequest::where('idempotency_key', $idempotencyKey)
                ->lockForUpdate()
                ->first();
            if ($existing) {
                return $existing;
            }

            $lockedPayment = PublicExecutionPaymentInstruction::whereKey($payment->id)
                ->lockForUpdate()
                ->firstOrFail();
            if ($lockedPayment->status !== 'executed' || ! $lockedPayment->executed_at) {
                throw new \RuntimeException('Only an executed public payment can be reversed.');
            }

            $plan = $lockedPayment->plan()->lockForUpdate()->firstOrFail();
            $this->assertManagerOrInspector((int) $plan->group_id, $actor);

            $reserved = (int) PublicExecutionReversalRequest::where('payment_instruction_id', $lockedPayment->id)
                ->whereIn('status', ['pending_approval', 'approved', 'executed'])
                ->lockForUpdate()
                ->sum('amount_gol');
            if ($reserved + $amountGol > (int) $lockedPayment->amount_gol) {
                throw new \RuntimeException('Requested reversals exceed the original executed payment amount.');
            }

            return PublicExecutionReversalRequest::create([
                'payment_instruction_id' => $lockedPayment->id,
                'created_by' => $actor->id,
                'amount_gol' => $amountGol,
                'status' => 'pending_approval',
                'idempotency_key' => trim($idempotencyKey),
                'reason' => trim($reason),
                'evidence' => $evidence,
                'metadata' => [
                    'governance_request_only' => true,
                    'automatic_reversal' => false,
                    'requires_distinct_second_approval' => true,
                    'requires_najm_bahar_execution' => true,
                    'original_payment_instruction_id' => (int) $lockedPayment->id,
                    'original_payment_transaction_id' => (int) (($lockedPayment->metadata ?? [])['payment_transaction_id'] ?? 0),
                    'plan_id' => (int) $lockedPayment->plan_id,
                    'group_id' => (int) $plan->group_id,
                ],
            ]);
        }, 3);
    }

    public function approve(PublicExecutionReversalRequest $request, User $actor): PublicExecutionReversalRequest
    {
        return DB::transaction(function () use ($request, $actor) {
            $locked = PublicExecutionReversalRequest::whereKey($request->id)->lockForUpdate()->firstOrFail();
            if ($locked->status === 'approved') {
                return $locked;
            }
            if ($locked->status !== 'pending_approval') {
                throw new \RuntimeException('Reversal request is not pending approval.');
            }
            if ((int) $locked->created_by === (int) $actor->id) {
                throw new \RuntimeException('Reversal request creator cannot approve their own request.');
            }

            $payment = $locked->paymentInstruction()->firstOrFail();
            $plan = $payment->plan()->firstOrFail();
            $this->assertManagerOrInspector((int) $plan->group_id, $actor);

            $locked->update([
                'status' => 'approved',
                'approved_by' => $actor->id,
                'approved_at' => now(),
            ]);

            return $locked->fresh();
        }, 3);
    }

    public function cancel(PublicExecutionReversalRequest $request, User $actor, string $reason): PublicExecutionReversalRequest
    {
        if (trim($reason) === '') {
            throw new \InvalidArgumentException('Reversal cancellation reason is required.');
        }

        return DB::transaction(function () use ($request, $actor, $reason) {
            $locked = PublicExecutionReversalRequest::whereKey($request->id)->lockForUpdate()->firstOrFail();
            if ($locked->status === 'cancelled') {
                return $locked;
            }
            if (! in_array($locked->status, ['pending_approval', 'approved'], true)) {
                throw new \RuntimeException('Only an unexecuted reversal request can be cancelled.');
            }

            $payment = $locked->paymentInstruction()->firstOrFail();
            $plan = $payment->plan()->firstOrFail();
            $this->assertManagerOrInspector((int) $plan->group_id, $actor);

            $locked->update([
                'status' => 'cancelled',
                'cancelled_by' => $actor->id,
                'cancelled_at' => now(),
                'cancellation_reason' => trim($reason),
            ]);

            return $locked->fresh();
        }, 3);
    }

    private function assertManagerOrInspector(int $groupId, User $actor): void
    {
        $membership = GroupUser::where('group_id', $groupId)
            ->where('user_id', $actor->id)
            ->where('status', 1)
            ->first();
        if (! $membership || ! in_array((int) $membership->role, [2, 3], true)) {
            throw new \RuntimeException('Only an active group manager or inspector may manage public payment reversal requests.');
        }
    }
}
