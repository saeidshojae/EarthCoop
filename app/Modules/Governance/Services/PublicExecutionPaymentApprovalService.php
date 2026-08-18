<?php

namespace App\Modules\Governance\Services;

use App\Models\GroupUser;
use App\Models\User;
use App\Modules\Governance\Models\PublicExecutionPaymentInstruction;
use Illuminate\Support\Facades\DB;

class PublicExecutionPaymentApprovalService
{
    public function approve(PublicExecutionPaymentInstruction $instruction, User $actor): PublicExecutionPaymentInstruction
    {
        return DB::transaction(function () use ($instruction, $actor) {
            $locked = PublicExecutionPaymentInstruction::whereKey($instruction->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($locked->status === 'approved' || $locked->status === 'executed') {
                return $locked;
            }
            if ($locked->status !== 'pending_approval') {
                throw new \RuntimeException('Public execution payment instruction is not awaiting approval.');
            }
            if ((int) $locked->created_by === (int) $actor->id) {
                throw new \RuntimeException('Payment instruction creator cannot approve their own instruction.');
            }

            $plan = $locked->plan()->lockForUpdate()->firstOrFail();
            $membership = GroupUser::where('group_id', $plan->group_id)
                ->where('user_id', $actor->id)
                ->where('status', 1)
                ->first();
            if (! $membership || ! in_array((int) $membership->role, [2, 3], true)) {
                throw new \RuntimeException('Only a distinct active group manager or inspector may approve public execution payment.');
            }

            $locked->update([
                'status' => 'approved',
                'approved_by' => $actor->id,
                'approved_at' => now(),
            ]);

            return $locked->fresh();
        }, 3);
    }

    public function cancel(
        PublicExecutionPaymentInstruction $instruction,
        User $actor,
        string $reason
    ): PublicExecutionPaymentInstruction {
        if (trim($reason) === '') {
            throw new \InvalidArgumentException('Cancellation reason is required.');
        }

        return DB::transaction(function () use ($instruction, $actor, $reason) {
            $locked = PublicExecutionPaymentInstruction::whereKey($instruction->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($locked->status === 'cancelled') {
                return $locked;
            }
            if ($locked->status === 'executed') {
                throw new \RuntimeException('Executed public payment cannot be cancelled; a separate reversal policy is required.');
            }
            if (! in_array($locked->status, ['pending_approval', 'approved'], true)) {
                throw new \RuntimeException('Public execution payment instruction cannot be cancelled from its current state.');
            }

            $plan = $locked->plan()->lockForUpdate()->firstOrFail();
            $membership = GroupUser::where('group_id', $plan->group_id)
                ->where('user_id', $actor->id)
                ->where('status', 1)
                ->first();
            if (! $membership || ! in_array((int) $membership->role, [2, 3], true)) {
                throw new \RuntimeException('Only an active group manager or inspector may cancel public execution payment.');
            }

            $locked->update([
                'status' => 'cancelled',
                'cancelled_by' => $actor->id,
                'cancelled_at' => now(),
                'cancellation_reason' => trim($reason),
            ]);

            return $locked->fresh();
        }, 3);
    }
}
