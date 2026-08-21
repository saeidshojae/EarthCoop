<?php

namespace App\Services\Elections;

use App\Models\ElectionPolicyVersion;
use App\Models\ElectionResponsibilityContractVersion;
use App\Models\GroupSetting;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class ElectionPolicyVersionService
{
    public function publishFromSetting(
        GroupSetting $setting,
        ?int $actorUserId = null,
        ?string $reason = null,
        ?CarbonInterface $effectiveAt = null,
        ?int $responseDurationDays = null,
    ): ElectionPolicyVersion {
        $effectiveAt ??= now();
        $reason = trim((string) ($reason ?: 'admin_policy_update'));
        if ($reason === '') {
            throw new InvalidArgumentException('Election policy change reason is required.');
        }

        return DB::transaction(function () use ($setting, $actorUserId, $reason, $effectiveAt, $responseDurationDays): ElectionPolicyVersion {
            $locked = GroupSetting::query()->lockForUpdate()->findOrFail($setting->id);
            $latest = ElectionPolicyVersion::query()
                ->where('group_setting_id', $locked->id)
                ->orderByDesc('version')
                ->lockForUpdate()
                ->first();

            $version = $latest === null ? 1 : ((int) $latest->version + 1);
            if ($latest !== null && ($latest->retired_at === null || $latest->retired_at->gt($effectiveAt))) {
                $latest->forceFill(['retired_at' => $effectiveAt])->save();
            }

            $managerContractId = $this->activeContractId('manager');
            $inspectorContractId = $this->activeContractId('inspector');

            return ElectionPolicyVersion::create([
                'group_setting_id' => $locked->id,
                'level_key' => $locked->level,
                'version' => $version,
                'election_status' => (bool) $locked->election_status,
                'manager_count' => max(0, (int) $locked->manager_count),
                'inspector_count' => max(0, (int) $locked->inspector_count),
                'voting_duration_days' => max(1, (int) $locked->election_time),
                'start_threshold' => max(1, (int) $locked->max_for_election),
                'cycle_interval_months' => max(0, (int) $locked->second_election_time),
                'response_duration_days' => max(1, (int) ($responseDurationDays ?? 7)),
                'manager_contract_version_id' => $managerContractId,
                'inspector_contract_version_id' => $inspectorContractId,
                'effective_at' => $effectiveAt,
                'created_by' => $actorUserId,
                'change_reason' => $reason,
                'metadata' => [
                    'source' => 'admin_group_setting',
                    'manager_contract_version_id' => $managerContractId,
                    'inspector_contract_version_id' => $inspectorContractId,
                ],
            ]);
        }, 3);
    }

    private function activeContractId(string $position): ?int
    {
        $id = ElectionResponsibilityContractVersion::query()
            ->where('position', $position)
            ->where('is_active', true)
            ->whereNotNull('published_at')
            ->orderByDesc('version')
            ->value('id');

        return $id === null ? null : (int) $id;
    }
}
