<?php

namespace App\Modules\NajmBahar\Services\Api;

use App\Models\Setting;
use App\Modules\NajmBahar\Models\MonetaryPolicyVersion;

class NajmBaharActivationPolicyService
{
    public function current(): array
    {
        $policy = MonetaryPolicyVersion::effective()
            ->orderByDesc('version')
            ->first();

        if ($policy instanceof MonetaryPolicyVersion) {
            return [
                'version_id' => (int) $policy->id,
                'version' => (int) $policy->version,
                'source' => 'versioned_policy',
                'parameters' => [
                    'reputation_conversion_enabled' => (bool) data_get(
                        $policy->parameters,
                        'reputation_conversion_enabled',
                        false,
                    ),
                    'reputation_to_gol_ratio' => max(
                        1,
                        (int) data_get($policy->parameters, 'reputation_to_gol_ratio', 100),
                    ),
                ],
            ];
        }

        // Read-only fallback. Do not use Setting::singleton() or
        // firstNajmBaharSettings(): eligibility must never migrate/save legacy
        // monetary fields merely because a native client performed a GET.
        $setting = Setting::query()->first();

        return [
            'version_id' => null,
            'version' => null,
            'source' => 'legacy_settings',
            'parameters' => [
                'reputation_conversion_enabled' => (bool) (
                    $setting?->reputation_conversion_enabled ?? false
                ),
                'reputation_to_gol_ratio' => max(
                    1,
                    (int) ($setting?->reputation_to_gol_ratio ?? 100),
                ),
            ],
        ];
    }
}
