<?php

namespace Database\Seeders;

use App\Enums\Membership\GroupCreationMode;
use App\Models\GovernanceCapabilityPolicy;
use App\Models\GroupCreationPolicy;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class StageCCanonicalGroupPolicySeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function (): void {
            GroupCreationPolicy::query()
                ->where('enabled', true)
                ->whereNull('governance_area_id')
                ->whereNull('governance_type')
                ->whereNull('governance_rank')
                ->where('priority', 0)
                ->whereHas('dimension', fn ($query) => $query->whereIn('key', [
                    'public',
                    'profession',
                    'specialty',
                    'age',
                    'gender',
                ]))
                ->get()
                ->each(function (GroupCreationPolicy $policy): void {
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

            if ($capabilityPolicy !== null) {
                $capabilities = $capabilityPolicy->capabilities ?? [];
                $capabilities['group_creation_mode'] = 'automatic';
                $capabilityPolicy->update(['capabilities' => $capabilities]);
            }
        });
    }
}
