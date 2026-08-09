<?php

namespace App\Modules\Governance\Services;

use App\Models\User;
use App\Modules\Governance\Models\EconomicAction;
use App\Modules\Governance\Models\EligibilitySnapshot;
use App\Modules\Governance\Models\PublicContributionObligation;
use App\Modules\Governance\Models\PublicContributionPlan;
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
                'status' => 'pending',
                'due_at' => $plan->due_at,
                'metadata' => [
                    'snapshot_position' => $position,
                    'automatic_debit' => false,
                ],
            ]);
        }, 3);
    }
}
