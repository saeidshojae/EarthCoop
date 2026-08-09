<?php

namespace App\Modules\Governance\Services;

use App\Models\User;
use App\Modules\Governance\Models\EconomicAction;
use App\Modules\Governance\Models\EligibilitySnapshot;
use App\Modules\Governance\Models\PublicContributionObligation;
use App\Modules\Governance\Models\PublicContributionPlan;
use App\Modules\NajmBahar\Services\AccountService;
use App\Modules\NajmBahar\Services\DimCommitmentService;
use Illuminate\Support\Facades\DB;

class PublicContributionService
{
    public function createPlan(EconomicAction $action, $dueAt = null): PublicContributionPlan
    {
        return DB::transaction(function () use ($action, $dueAt) {
            $locked = EconomicAction::whereKey($action->id)->lockForUpdate()->firstOrFail();

            if ($locked->action_type !== ResolutionEconomicBridge::PUBLIC_PROJECT_APPROVED) {
                throw new \RuntimeException('Only approved public-project actions can create public contribution plans.');
            }

            $existing = PublicContributionPlan::where('economic_action_id', $locked->id)->first();
            if ($existing) {
                return $existing;
            }

            if ($locked->status !== 'pending') {
                throw new \RuntimeException('Economic action is not pending contribution-plan execution.');
            }

            $snapshot = EligibilitySnapshot::whereKey($locked->eligibility_snapshot_id)
                ->where('status', 'finalized')
                ->first();
            if (! $snapshot || (int) $snapshot->eligible_count <= 0) {
                throw new \RuntimeException('A finalized non-empty eligibility snapshot is required.');
            }

            $effect = (array) (($locked->payload ?? [])['financial_effect'] ?? []);
            $totalRequiredGol = (int) ($effect['requested_capital_gol'] ?? 0);
            if ($totalRequiredGol <= 0) {
                throw new \InvalidArgumentException('Public project capital must be a positive integer number of Gol.');
            }

            $eligibleCount = (int) $snapshot->eligible_count;
            $baseAmount = intdiv($totalRequiredGol, $eligibleCount);
            $remainder = $totalRequiredGol % $eligibleCount;

            $plan = PublicContributionPlan::create([
                'economic_action_id' => $locked->id,
                'resolution_id' => $locked->resolution_id,
                'group_id' => $locked->group_id,
                'eligibility_snapshot_id' => $snapshot->id,
                'status' => 'open',
                'total_required_gol' => $totalRequiredGol,
                'eligible_count' => $eligibleCount,
                'base_amount_gol' => $baseAmount,
                'remainder_gol' => $remainder,
                'due_at' => $dueAt,
                'opened_at' => now(),
                'metadata' => [
                    'allocation_rule' => 'equal_integer_gol_with_deterministic_remainder',
                    'remainder_rule' => 'one_extra_gol_to_first_members_in_snapshot_order',
                    'automatic_debit' => false,
                ],
            ]);

            $locked->update([
                'status' => 'completed',
                'attempts' => (int) $locked->attempts + 1,
                'result' => [
                    'public_contribution_plan_id' => (int) $plan->id,
                    'total_required_gol' => $totalRequiredGol,
                    'eligible_count' => $eligibleCount,
                ],
                'completed_at' => now(),
            ]);

            $resolution = $locked->resolution()->lockForUpdate()->first();
            if ($resolution) {
                $metadata = (array) ($resolution->metadata ?? []);
                $metadata['public_contribution_plan_id'] = (int) $plan->id;
                $metadata['economic_bridge_applied'] = true;
                $metadata['economic_effect_executed'] = false;
                $resolution->update([
                    'effect_status' => 'bridged',
                    'metadata' => $metadata,
                ]);
            }

            return $plan;
        }, 3);
    }

    public function obligationForUser(PublicContributionPlan $plan, User $user): PublicContributionObligation
    {
        if ($plan->status !== 'open') {
            throw new \RuntimeException('Public contribution plan is not open.');
        }

        return DB::transaction(function () use ($plan, $user) {
            $existing = PublicContributionObligation::where('plan_id', $plan->id)
                ->where('user_id', $user->id)
                ->lockForUpdate()
                ->first();
            if ($existing) {
                return $existing;
            }

            $snapshot = $plan->eligibilitySnapshot;
            $position = app(EligibilitySnapshotService::class)->memberPosition($snapshot, (int) $user->id);
            if ($position === null) {
                throw new \RuntimeException('User was not part of the immutable eligible-member snapshot.');
            }

            $amount = (int) $plan->base_amount_gol;
            if ($position < (int) $plan->remainder_gol) {
                $amount++;
            }

            return PublicContributionObligation::create([
                'plan_id' => $plan->id,
                'user_id' => $user->id,
                'amount_gol' => $amount,
                'paid_gol' => 0,
                'committed_gol' => 0,
                'status' => 'pending',
                'due_at' => $plan->due_at,
                'metadata' => [
                    'snapshot_position' => $position,
                    'automatic_debit' => false,
                ],
            ]);
        }, 3);
    }

    public function commitDim(PublicContributionObligation $obligation, User $user): PublicContributionObligation
    {
        return DB::transaction(function () use ($obligation, $user) {
            $locked = PublicContributionObligation::whereKey($obligation->id)->lockForUpdate()->firstOrFail();
            if ((int) $locked->user_id !== (int) $user->id) {
                throw new \RuntimeException('Only the obligated member may commit Dim for this obligation.');
            }
            if ($locked->status === 'committed' && (int) $locked->committed_gol === (int) $locked->amount_gol) {
                return $locked;
            }
            if ($locked->status !== 'pending' || (int) $locked->paid_gol !== 0 || (int) $locked->committed_gol !== 0) {
                throw new \RuntimeException('Public contribution obligation is not available for Dim commitment.');
            }

            $account = app(AccountService::class)->getMainAccountForUser((int) $user->id);
            if (! $account) {
                throw new \RuntimeException('Member has no Najm Bahar main account.');
            }

            $amount = (int) $locked->amount_gol;
            $transaction = app(DimCommitmentService::class)->commit(
                $account,
                $amount,
                'Commit Dim for public contribution obligation #' . $locked->id,
                'public-contribution:obligation:' . $locked->id . ':dim-commit',
                [
                    'public_contribution_obligation_id' => (int) $locked->id,
                    'public_contribution_plan_id' => (int) $locked->plan_id,
                    'user_id' => (int) $user->id,
                ]
            );

            $metadata = (array) ($locked->metadata ?? []);
            $metadata['dim_commitment_transaction_id'] = (int) $transaction->id;
            $metadata['automatic_debit'] = false;
            $locked->update([
                'committed_gol' => $amount,
                'status' => 'committed',
                'committed_at' => now(),
                'metadata' => $metadata,
            ]);

            return $locked->fresh();
        }, 3);
    }

    public function releaseDim(PublicContributionObligation $obligation, User $user): PublicContributionObligation
    {
        return DB::transaction(function () use ($obligation, $user) {
            $locked = PublicContributionObligation::whereKey($obligation->id)->lockForUpdate()->firstOrFail();
            if ((int) $locked->user_id !== (int) $user->id) {
                throw new \RuntimeException('Only the obligated member may release this Dim commitment.');
            }
            if ($locked->status === 'pending' && (int) $locked->committed_gol === 0) {
                return $locked;
            }
            if ($locked->status !== 'committed' || (int) $locked->paid_gol !== 0) {
                throw new \RuntimeException('Only an unpaid committed obligation may release Dim.');
            }

            $account = app(AccountService::class)->getMainAccountForUser((int) $user->id);
            if (! $account) {
                throw new \RuntimeException('Member has no Najm Bahar main account.');
            }

            $amount = (int) $locked->committed_gol;
            $transaction = app(DimCommitmentService::class)->release(
                $account,
                $amount,
                'Release Dim for public contribution obligation #' . $locked->id,
                'public-contribution:obligation:' . $locked->id . ':dim-release',
                [
                    'public_contribution_obligation_id' => (int) $locked->id,
                    'public_contribution_plan_id' => (int) $locked->plan_id,
                    'user_id' => (int) $user->id,
                ]
            );

            $metadata = (array) ($locked->metadata ?? []);
            $metadata['dim_release_transaction_id'] = (int) $transaction->id;
            $locked->update([
                'committed_gol' => 0,
                'status' => 'pending',
                'committed_at' => null,
                'metadata' => $metadata,
            ]);

            return $locked->fresh();
        }, 3);
    }
}
