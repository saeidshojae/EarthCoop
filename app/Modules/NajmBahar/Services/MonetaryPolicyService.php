<?php

namespace App\Modules\NajmBahar\Services;

use App\Helpers\BaharMoney;
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
                'membership_fee_gol' => $this->positiveLegacyAmount(
                    $settings?->najm_bahar_membership_fee_amount,
                    BaharMoney::toGolFromBahar(12)
                ),
                'membership_operations_gol' => $this->positiveLegacyAmount(
                    $settings?->najm_bahar_membership_fee_membership_amount,
                    BaharMoney::toGolFromBahar(6)
                ),
                'membership_insurance_gol' => $this->positiveLegacyAmount(
                    $settings?->najm_bahar_membership_fee_insurance_amount,
                    BaharMoney::toGolFromBahar(3)
                ),
                'membership_burn_gol' => $this->positiveLegacyAmount(
                    $settings?->najm_bahar_membership_fee_burn_amount,
                    BaharMoney::toGolFromBahar(3)
                ),
                // Observation only. No collection is enabled by these values.
                'idle_observation_period_days' => 180,
                'idle_observation_exempt_balance_gol' => 0,
                'idle_tax_enabled' => false,
                'idle_tax_rate_bps' => 0,
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

    private function positiveLegacyAmount(mixed $value, int $default): int
    {
        $amount = is_numeric($value) ? (int) $value : 0;

        return $amount > 0 ? $amount : $default;
    }
}
