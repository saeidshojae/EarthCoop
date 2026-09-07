<?php

namespace App\Services\Elections;

use App\Models\Election;
use App\Models\ElectionPolicyOverride;
use App\Models\ElectionPolicyVersion;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

class ElectionActiveCyclePolicyOverrideService
{
    public function apply(Election $election, ElectionPolicyVersion $target, User $actor, string $reason): ElectionPolicyOverride
    {
        $reason = trim($reason);
        if ($reason === '') throw new InvalidArgumentException('Explicit active-cycle policy override reason is required.');
        if ($target->effective_at === null || $target->effective_at->isFuture()) {
            throw new InvalidArgumentException('A future policy version cannot be forced into a current cycle before its effective time.');
        }

        return DB::transaction(function () use ($election,$target,$actor,$reason): ElectionPolicyOverride {
            $locked = Election::query()->with('policyVersion')->lockForUpdate()->findOrFail($election->id);
            $from = $locked->policyVersion;
            if ($from === null) throw new RuntimeException('Current cycle has no pinned policy version; override is fail-closed.');
            if ((int)$from->id === (int)$target->id) throw new InvalidArgumentException('Target policy is already pinned to this cycle.');
            if ((int)$from->group_setting_id !== (int)$target->group_setting_id || (string)$from->level_key !== (string)$target->level_key) {
                throw new InvalidArgumentException('Override target must belong to the same election policy scope.');
            }

            $status = $locked->lifecycle_status instanceof \BackedEnum ? $locked->lifecycle_status->value : (string)$locked->lifecycle_status;
            $audit = ElectionPolicyOverride::create([
                'election_id'=>$locked->id,
                'from_policy_version_id'=>$from->id,
                'to_policy_version_id'=>$target->id,
                'actor_user_id'=>$actor->id,
                'reason'=>$reason,
                'lifecycle_status'=>$status,
                'metadata'=>[
                    'explicit_override'=>true,
                    'from_version'=>(int)$from->version,
                    'to_version'=>(int)$target->version,
                    'from_effective_at'=>optional($from->effective_at)->toISOString(),
                    'to_effective_at'=>optional($target->effective_at)->toISOString(),
                ],
                'applied_at'=>now(),
            ]);

            $locked->forceFill(['policy_version_id'=>$target->id])->save();
            return $audit->refresh();
        },3);
    }
}
