<?php

namespace Database\Seeders;

use App\Enums\Membership\GroupCreationMode;
use App\Models\GovernanceCapabilityPolicy;
use App\Models\GroupCreationPolicy;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class StageCCanonicalGroupPolicySeeder extends Seeder
{
    private const DIMENSIONS = [
        'public',
        'profession',
        'specialty',
        'age',
        'gender',
    ];

    public function run(): void
    {
        DB::transaction(function (): void {
            $policies = GroupCreationPolicy::query()
                ->with('dimension')
                ->where('enabled', true)
                ->whereNull('governance_area_id')
                ->whereNull('governance_type')
                ->whereNull('governance_rank')
                ->where('priority', 0)
                ->whereHas('dimension', fn ($query) => $query->whereIn('key', self::DIMENSIONS))
                ->get();

            $foundDimensions = $policies
                ->pluck('dimension.key')
                ->filter()
                ->sort()
                ->values()
                ->all();
            $expectedDimensions = collect(self::DIMENSIONS)->sort()->values()->all();

            if ($foundDimensions !== $expectedDimensions) {
                throw new RuntimeException('Stage C canonical group policies are incomplete; refusing partial activation.');
            }

            $policies->each(function (GroupCreationPolicy $policy): void {
                $metadata = $policy->metadata ?? [];
                $metadata['stage_c_canonical_groups'] = true;

                $policy->update([
                    'mode' => GroupCreationMode::Automatic,
                    'threshold' => null,
                    'metadata' => $metadata,
                ]);
            });

            $capabilityPolicy = GovernanceCapabilityPolicy::query()
                ->where('scope', 'default')
                ->whereNull('country_code')
                ->whereNull('governance_type')
                ->first();

            if ($capabilityPolicy === null) {
                throw new RuntimeException('Default governance capability policy is missing; refusing Stage C activation.');
            }

            $capabilities = $capabilityPolicy->capabilities ?? [];
            $capabilities['group_creation_mode'] = 'automatic';
            $capabilityPolicy->update(['capabilities' => $capabilities]);
        });
    }
}
