<?php

namespace App\Services\Elections;

use App\Models\Election;
use App\Models\ElectionPolicyVersion;
use App\Models\ElectionResponsibilityContractVersion;
use App\Models\Group;
use App\Models\GroupSetting;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class ElectionPolicyResolver
{
    public function resolveForGroup(Group $group): GroupSetting
    {
        $setting = $this->baseSettingForGroup($group);

        $active = Election::query()
            ->where('group_id', $group->id)
            ->whereNotNull('policy_version_id')
            ->whereIn('lifecycle_status', [
                'scheduled', 'open', 'closed', 'tallying', 'awaiting_acceptance', 'appointing',
            ])
            ->orderByDesc('cycle_number')
            ->orderByDesc('id')
            ->first();

        if ($active === null) return $setting;
        $policy = $active->policyVersion()->first();
        if ($policy === null) return $setting;

        $projection = new GroupSetting();
        $projection->setRawAttributes([
            'id' => $setting->id,
            'level' => $setting->level,
            'manager_count' => $policy->manager_count,
            'inspector_count' => $policy->inspector_count,
            'election_time' => $policy->voting_duration_days,
            'max_for_election' => $policy->start_threshold,
            'election_status' => $policy->election_status ? 1 : 0,
            'second_election_time' => $policy->cycle_interval_months,
        ], true);

        return $projection;
    }

    public function resolveEffectiveForGroup(Group $group): ElectionPolicyVersion
    {
        $setting = $this->baseSettingForGroup($group);
        $policy = ElectionPolicyVersion::query()
            ->where('group_setting_id', $setting->id)
            ->where('effective_at', '<=', now())
            ->where(function ($query) {
                $query->whereNull('retired_at')->orWhere('retired_at', '>', now());
            })
            ->orderByDesc('version')
            ->first();

        return $policy ?? $this->createCompatibilityBaseline($setting);
    }

    public function resolveForElection(Election $election): ElectionPolicyVersion
    {
        $policy = $election->policyVersion()->first();
        if ($policy === null) {
            throw new RuntimeException("Election [{$election->id}] does not have a frozen policy version.");
        }
        return $policy;
    }

    public function levelKeyForGroup(Group $group): string
    {
        $attributes = $group->getAttributes();
        $base = strtolower(trim((string) ($attributes['location_level'] ?? '')));

        // Historical group data uses `section` while the canonical GroupSetting
        // vocabulary uses `district`. Normalize once at the policy boundary so
        // every lifecycle stage resolves the same versioned rule set.
        if ($base === 'section') {
            $base = 'district';
        }

        if (($attributes['specialty_id'] ?? null) !== null) return $base.'_job';
        if (($attributes['experience_id'] ?? null) !== null) return $base.'_experience';
        if (($attributes['age_group_id'] ?? null) !== null) return $base.'_age';
        if (($attributes['gender'] ?? null) !== null) return $base.'_gender';
        return $base;
    }

    public function managerSeatCount(Model $setting): int { return max(0, (int) $setting->manager_count); }
    public function inspectorSeatCount(Model $setting): int { return max(0, (int) $setting->inspector_count); }
    public function electionEnabled(Model $setting): bool { return (bool) $setting->election_status; }

    public function votingDurationDays(Model $setting): int
    {
        return $setting instanceof ElectionPolicyVersion
            ? max(1, (int) $setting->voting_duration_days)
            : max(1, (int) $setting->election_time);
    }

    public function startThreshold(Model $setting): int
    {
        return $setting instanceof ElectionPolicyVersion
            ? max(1, (int) $setting->start_threshold)
            : max(1, (int) $setting->max_for_election);
    }

    public function cycleIntervalMonths(Model $setting): int
    {
        return $setting instanceof ElectionPolicyVersion
            ? max(0, (int) $setting->cycle_interval_months)
            : max(0, (int) $setting->second_election_time);
    }

    public function responseDurationDays(Model $setting): int
    {
        return $setting instanceof ElectionPolicyVersion
            ? max(1, (int) $setting->response_duration_days)
            : ElectionResponsibilityOfferService::RESPONSE_WINDOW_DAYS;
    }

    private function baseSettingForGroup(Group $group): GroupSetting
    {
        $level = $this->levelKeyForGroup($group);
        $setting = GroupSetting::query()->where('level', $level)->first();
        if ($setting === null) {
            throw new RuntimeException("Election policy is not configured for group setting level [{$level}].");
        }
        return $setting;
    }

    private function createCompatibilityBaseline(GroupSetting $setting): ElectionPolicyVersion
    {
        return DB::transaction(function () use ($setting): ElectionPolicyVersion {
            $existing = ElectionPolicyVersion::query()
                ->where('group_setting_id', $setting->id)
                ->where('effective_at', '<=', now())
                ->orderByDesc('version')
                ->lockForUpdate()
                ->first();
            if ($existing !== null) return $existing;

            $nextVersion = ((int) ElectionPolicyVersion::query()
                ->where('group_setting_id', $setting->id)->max('version')) + 1;

            return ElectionPolicyVersion::create([
                'group_setting_id' => $setting->id,
                'level_key' => $setting->level,
                'version' => max(1, $nextVersion),
                'election_status' => (bool) $setting->election_status,
                'manager_count' => max(0, (int) $setting->manager_count),
                'inspector_count' => max(0, (int) $setting->inspector_count),
                'voting_duration_days' => max(1, (int) $setting->election_time),
                'start_threshold' => max(1, (int) $setting->max_for_election),
                'cycle_interval_months' => max(0, (int) $setting->second_election_time),
                'response_duration_days' => ElectionResponsibilityOfferService::RESPONSE_WINDOW_DAYS,
                'manager_contract_version_id' => $this->activeContractId('manager'),
                'inspector_contract_version_id' => $this->activeContractId('inspector'),
                'effective_at' => now(),
                'change_reason' => 'compatibility_baseline_created_on_first_use',
                'metadata' => ['source' => 'resolver_compatibility_fallback'],
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
