<?php

namespace App\Modules\Governance\Services;

use App\Models\GroupUser;
use App\Models\User;
use App\Modules\Governance\Models\PublicContributionPlan;
use App\Modules\Governance\Models\PublicExecutionAuthorization;
use Illuminate\Support\Facades\DB;

class PublicExecutionAuthorizationService
{
    public function authorize(PublicContributionPlan $plan, User $actor, array $conditions = []): PublicExecutionAuthorization
    {
        return DB::transaction(function () use ($plan, $actor, $conditions) {
            $locked = PublicContributionPlan::whereKey($plan->id)->lockForUpdate()->firstOrFail();

            $existing = PublicExecutionAuthorization::where('plan_id', $locked->id)->first();
            if ($existing) {
                return $existing;
            }

            if ($locked->status !== 'fully_committed'
                || (int) $locked->committed_total_gol !== (int) $locked->total_required_gol) {
                throw new \RuntimeException('Public funding must be fully committed before execution authorization.');
            }

            $membership = GroupUser::where('group_id', $locked->group_id)
                ->where('user_id', $actor->id)
                ->where('status', 1)
                ->first();
            if (! $membership || ! in_array((int) $membership->role, [2, 3], true)) {
                throw new \RuntimeException('Only an active group manager or inspector may authorize execution.');
            }

            $authorization = PublicExecutionAuthorization::create([
                'plan_id' => $locked->id,
                'authorized_by' => $actor->id,
                'status' => 'authorized',
                'conditions' => $conditions,
                'authorized_at' => now(),
            ]);

            $metadata = (array) ($locked->metadata ?? []);
            $metadata['execution_authorization_id'] = (int) $authorization->id;
            $metadata['execution_authorized'] = true;
            $metadata['monetary_execution_started'] = false;
            $locked->update([
                'status' => 'execution_authorized',
                'metadata' => $metadata,
            ]);

            return $authorization;
        }, 3);
    }
}
