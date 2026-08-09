<?php

namespace App\Modules\Governance\Services;

use App\Modules\Governance\Models\EconomicAction;
use App\Modules\Governance\Models\PublicContributionPlan;
use App\Modules\Governance\Models\PublicExecutionAuthorization;
use Illuminate\Support\Facades\DB;

class PublicExecutionBridge
{
    public const PUBLIC_PROJECT_EXECUTION_AUTHORIZED = 'PUBLIC_PROJECT_EXECUTION_AUTHORIZED';

    public function enqueue(PublicExecutionAuthorization $authorization): EconomicAction
    {
        return DB::transaction(function () use ($authorization) {
            $lockedAuthorization = PublicExecutionAuthorization::whereKey($authorization->id)
                ->lockForUpdate()
                ->firstOrFail();
            if ($lockedAuthorization->status !== 'authorized') {
                throw new \RuntimeException('Execution authorization is not active.');
            }

            $plan = PublicContributionPlan::whereKey($lockedAuthorization->plan_id)
                ->lockForUpdate()
                ->firstOrFail();

            $key = 'public-execution:authorization:' . $lockedAuthorization->id;
            $existing = EconomicAction::where('idempotency_key', $key)
                ->lockForUpdate()
                ->first();
            if ($existing) {
                return $existing;
            }

            if ($plan->status !== 'execution_authorized'
                || (int) $plan->committed_total_gol !== (int) $plan->total_required_gol) {
                throw new \RuntimeException('Execution bridge requires an authorized fully committed plan.');
            }

            $action = EconomicAction::create([
                'resolution_id' => $plan->resolution_id,
                'group_id' => $plan->group_id,
                'eligibility_snapshot_id' => $plan->eligibility_snapshot_id,
                'action_type' => self::PUBLIC_PROJECT_EXECUTION_AUTHORIZED,
                'status' => 'pending',
                'idempotency_key' => $key,
                'payload' => [
                    'public_contribution_plan_id' => (int) $plan->id,
                    'execution_authorization_id' => (int) $lockedAuthorization->id,
                    'committed_total_gol' => (int) $plan->committed_total_gol,
                    'total_required_gol' => (int) $plan->total_required_gol,
                    'conditions' => (array) ($lockedAuthorization->conditions ?? []),
                    'required_effect' => 'activate_committed_dim_then_execute_approved_public_payment',
                    'governance_may_move_money' => false,
                ],
                'attempts' => 0,
            ]);

            $metadata = (array) ($plan->metadata ?? []);
            $metadata['execution_economic_action_id'] = (int) $action->id;
            $metadata['monetary_execution_started'] = false;
            $plan->update([
                'status' => 'execution_queued',
                'metadata' => $metadata,
            ]);

            return $action;
        }, 3);
    }
}
