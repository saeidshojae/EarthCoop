<?php

namespace Tests\Feature\NajmBahar;

use App\Helpers\BaharMoney;
use App\Modules\NajmBahar\Models\MonetaryPolicyVersion;
use App\Modules\NajmBahar\Services\MonetaryPolicyService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MonetaryPolicyServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_latest_effective_active_policy_is_resolved(): void
    {
        MonetaryPolicyVersion::create([
            'version' => 1,
            'status' => 'active',
            'parameters' => [
                'reputation_conversion_enabled' => true,
                'reputation_to_gol_ratio' => 100,
                'auto_activation_enabled' => false,
            ],
            'effective_from' => now()->subDay(),
        ]);

        $latest = MonetaryPolicyVersion::create([
            'version' => 2,
            'status' => 'active',
            'parameters' => [
                'reputation_conversion_enabled' => true,
                'reputation_to_gol_ratio' => 250,
                'auto_activation_enabled' => true,
                'auto_activation_period' => 'monthly',
                'auto_activation_amount_gol' => 1000,
            ],
            'effective_from' => now()->subHour(),
        ]);

        $policy = app(MonetaryPolicyService::class)->current();

        $this->assertSame('versioned_policy', $policy['source']);
        $this->assertSame($latest->id, $policy['version_id']);
        $this->assertSame(2, $policy['version']);
        $this->assertSame(250, data_get($policy, 'parameters.reputation_to_gol_ratio'));
        $this->assertSame(1000, data_get($policy, 'parameters.auto_activation_amount_gol'));
    }

    public function test_future_and_expired_policies_are_not_effective(): void
    {
        MonetaryPolicyVersion::create([
            'version' => 1,
            'status' => 'active',
            'parameters' => ['reputation_to_gol_ratio' => 50],
            'effective_from' => now()->subDays(2),
            'effective_until' => now()->subDay(),
        ]);

        MonetaryPolicyVersion::create([
            'version' => 2,
            'status' => 'active',
            'parameters' => ['reputation_to_gol_ratio' => 25],
            'effective_from' => now()->addDay(),
        ]);

        $policy = app(MonetaryPolicyService::class)->current();

        $this->assertSame('legacy_settings', $policy['source']);
        $this->assertNull($policy['version_id']);
    }

    public function test_fallback_membership_policy_is_nonzero_and_idle_collection_is_disabled(): void
    {
        $policy = app(MonetaryPolicyService::class)->current();

        $this->assertSame(BaharMoney::toGolFromBahar(12), data_get($policy, 'parameters.membership_fee_gol'));
        $this->assertSame(BaharMoney::toGolFromBahar(6), data_get($policy, 'parameters.membership_operations_gol'));
        $this->assertSame(BaharMoney::toGolFromBahar(3), data_get($policy, 'parameters.membership_insurance_gol'));
        $this->assertSame(BaharMoney::toGolFromBahar(3), data_get($policy, 'parameters.membership_burn_gol'));
        $this->assertFalse((bool) data_get($policy, 'parameters.idle_tax_enabled'));
        $this->assertSame(0, (int) data_get($policy, 'parameters.idle_tax_rate_bps'));
    }
}
