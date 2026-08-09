<?php

namespace App\Modules\NajmBahar\Services;

use App\Models\Setting;
use App\Modules\NajmBahar\Models\MonetaryPolicyVersion;

class MonetaryPolicyService
{
    public function current(): array
    {
        $policy = MonetaryPolicyVersion::effective()
            ->orderByDesc('version')
            ->first();

        if ($policy) {
            return [
                'version_id' => $policy->id,
                'version' => $policy->version,
                'source' => 'versioned_policy',
                'parameters' => $policy->parameters ?? [],
            ];
        }

        $settings = Setting::firstNajmBaharSettings();

        return [
            'version_id' => null,
            'version' => null,
            'source' => 'legacy_settings',
            'parameters' => [
                'reputation_conversion_enabled' => (bool) ($settings?->reputation_conversion_enabled ?? false),
                'reputation_to_gol_ratio' => (int) ($settings?->reputation_to_gol_ratio ?? 100),
                'auto_activation_enabled' => (bool) ($settings?->najm_bahar_auto_activation_enabled ?? false),
                'auto_activation_period' => (string) ($settings?->najm_bahar_auto_activation_period ?? 'monthly'),
                'auto_activation_amount_gol' => (int) ($settings?->najm_bahar_auto_activation_amount ?? 0),
                'membership_fee_gol' => (int) ($settings?->najm_bahar_membership_fee ?? 0),
                'membership_operations_gol' => (int) ($settings?->najm_bahar_membership_fee_membership_amount ?? 0),
                'membership_insurance_gol' => (int) ($settings?->najm_bahar_membership_fee_insurance_amount ?? 0),
                'membership_burn_gol' => (int) ($settings?->najm_bahar_membership_fee_burn_amount ?? 0),
            ],
        ];
    }

    public function parameter(string $key, mixed $default = null): mixed
    {
        return data_get($this->current(), 'parameters.' . $key, $default);
    }

    public function versionId(): ?int
    {
        return $this->current()['version_id'];
    }
}
