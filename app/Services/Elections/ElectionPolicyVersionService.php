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
        return $this->publishSnapshot(
            $setting,
            [
                'election_status' => (bool) $setting->election_status,
                'manager_count' => (int) $setting->manager_count,
                'inspector_count' => (int) $setting->inspector_count,
                'voting_duration_days' => (int) $setting->election_time,
                'start_threshold' => (int) $setting->max_for_election,
                'cycle_interval_months' => (int) $setting->second_election_time,
                'response_duration_days' => $responseDurationDays,
                'report_min_distinct_voters' => (int) ($setting->election_report_min_distinct_voters ?: 10),
                'report_bucket_days' => (int) ($setting->election_report_bucket_days ?: 7),
                'meaningful_trend_min_net_change' => (int) ($setting->election_meaningful_trend_min_net_change ?: 3),
            ],
            $actorUserId,
            $reason,
            $effectiveAt,
        );
    }

    public function publishSnapshot(
        GroupSetting $setting,
        array $snapshot,
        ?int $actorUserId = null,
        ?string $reason = null,
        ?CarbonInterface $effectiveAt = null,
    ): ElectionPolicyVersion {
        $effectiveAt ??= now();
        $reason = trim((string) ($reason ?: 'admin_policy_update'));
        if ($reason === '') {
            throw new InvalidArgumentException('Election policy change reason is required.');
        }

        return DB::transaction(function () use ($setting, $snapshot, $actorUserId, $reason, $effectiveAt): ElectionPolicyVersion {
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
                'election_status' => (bool) ($snapshot['election_status'] ?? $locked->election_status),
                'manager_count' => max(0, (int) ($snapshot['manager_count'] ?? $locked->manager_count)),
                'inspector_count' => max(0, (int) ($snapshot['inspector_count'] ?? $locked->inspector_count)),
                'voting_duration_days' => max(1, (int) ($snapshot['voting_duration_days'] ?? $locked->election_time)),
                'start_threshold' => max(1, (int) ($snapshot['start_threshold'] ?? $locked->max_for_election)),
                'cycle_interval_months' => max(0, (int) ($snapshot['cycle_interval_months'] ?? $locked->second_election_time)),
                'response_duration_days' => max(1, (int) ($snapshot['response_duration_days'] ?? 7)),
                'report_min_distinct_voters' => max(2, (int) ($snapshot['report_min_distinct_voters'] ?? $locked->election_report_min_distinct_voters ?? 10)),
                'report_bucket_days' => max(1, (int) ($snapshot['report_bucket_days'] ?? $locked->election_report_bucket_days ?? 7)),
                'meaningful_trend_min_net_change' => max(1, (int) ($snapshot['meaningful_trend_min_net_change'] ?? $locked->election_meaningful_trend_min_net_change ?? 3)),
                'manager_contract_version_id' => $managerContractId,
                'inspector_contract_version_id' => $inspectorContractId,
                'effective_at' => $effectiveAt,
                'created_by' => $actorUserId,
                'change_reason' => $reason,
                'metadata' => [
                    'source' => 'admin_group_setting',
                    'manager_contract_version_id' => $managerContractId,
                    'inspector_contract_version_id' => $inspectorContractId,
                    'scheduled' => $effectiveAt->isFuture(),
                ],
            ]);
        }, 3);
    }

    public function syncEffectiveMirrors(int $limit = 500): int
    {
        $limit = max(1, min(5000, $limit));
        $synced = 0;

        $settingIds = ElectionPolicyVersion::query()
            ->where('effective_at', '<=', now())
            ->where(function ($query) {
                $query->whereNull('retired_at')->orWhere('retired_at', '>', now());
            })
            ->orderBy('group_setting_id')
            ->distinct()
            ->limit($limit)
            ->pluck('group_setting_id');

        foreach ($settingIds as $settingId) {
            DB::transaction(function () use ($settingId, &$synced): void {
                $setting = GroupSetting::query()->lockForUpdate()->find($settingId);
                if ($setting === null) {
                    return;
                }

                $policy = ElectionPolicyVersion::query()
                    ->where('group_setting_id', $setting->id)
                    ->where('effective_at', '<=', now())
                    ->where(function ($query) {
                        $query->whereNull('retired_at')->orWhere('retired_at', '>', now());
                    })
                    ->orderByDesc('version')
                    ->first();

                if ($policy === null) {
                    return;
                }

                $mirror = [
                    'election_status' => $policy->election_status ? 1 : 0,
                    'manager_count' => (int) $policy->manager_count,
                    'inspector_count' => (int) $policy->inspector_count,
                    'election_time' => (int) $policy->voting_duration_days,
                    'max_for_election' => (int) $policy->start_threshold,
                    'second_election_time' => (int) $policy->cycle_interval_months,
                    'election_report_min_distinct_voters' => (int) $policy->report_min_distinct_voters,
                    'election_report_bucket_days' => (int) $policy->report_bucket_days,
                    'election_meaningful_trend_min_net_change' => (int) $policy->meaningful_trend_min_net_change,
                ];

                $dirty = false;
                foreach ($mirror as $column => $value) {
                    if ((int) $setting->{$column} !== (int) $value) {
                        $dirty = true;
                        break;
                    }
                }

                if (! $dirty) {
                    return;
                }

                $setting->forceFill($mirror)->save();
                $synced++;
            }, 3);
        }

        return $synced;
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
