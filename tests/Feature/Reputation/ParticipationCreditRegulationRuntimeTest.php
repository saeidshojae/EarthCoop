<?php

namespace Tests\Feature\Reputation;

use App\Models\ReputationRule;
use App\Modules\NajmBahar\Services\MonetaryPolicyService;
use App\Services\ParticipationCreditRegulationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class ParticipationCreditRegulationRuntimeTest extends TestCase
{
    use RefreshDatabase;

    public function test_snapshot_reflects_current_database_tiers_and_monetary_policy_without_blade_numbers(): void
    {
        config(['reputation.tiers.Silver' => 321]);

        $rule = ReputationRule::updateOrCreate(
            ['key' => 'post_created'],
            [
                'label' => 'ایجاد پست',
                'weight' => 77,
                'description' => 'قاعده آزمایشی جاری',
                'module' => 'content',
                'active' => true,
                'daily_cap' => 88,
                'dimension' => 'expertise',
                'convertible' => true,
                'repeat_policy' => 'daily',
            ]
        );

        $policy = Mockery::mock(MonetaryPolicyService::class);
        $policy->shouldReceive('current')->twice()->andReturn([
            'version_id' => null,
            'version' => 9,
            'source' => 'versioned_policy',
            'parameters' => [
                'reputation_conversion_enabled' => true,
                'reputation_to_gol_ratio' => 37,
            ],
        ]);

        $service = new ParticipationCreditRegulationService($policy);
        $first = $service->snapshot();
        $firstRule = collect($first['actionRules'])->firstWhere('key', 'post_created');
        $silver = collect($first['tiers'])->firstWhere('key', 'Silver');

        $this->assertSame(77, $firstRule['weight']);
        $this->assertSame(88, $firstRule['daily_cap']);
        $this->assertSame('expertise', $firstRule['dimension']);
        $this->assertTrue($firstRule['convertible']);
        $this->assertSame(321, $silver['minimum_points']);
        $this->assertTrue($first['conversion']['enabled']);
        $this->assertSame(37, $first['conversion']['points_per_gol']);
        $this->assertSame(3700, $first['conversion']['points_per_bahar']);
        $this->assertSame(9, $first['policySnapshot']['monetary_version']);

        $rule->update(['weight' => 91, 'daily_cap' => 144, 'convertible' => false]);
        config(['reputation.tiers.Silver' => 444]);

        $second = $service->snapshot();
        $secondRule = collect($second['actionRules'])->firstWhere('key', 'post_created');
        $secondSilver = collect($second['tiers'])->firstWhere('key', 'Silver');

        $this->assertSame(91, $secondRule['weight']);
        $this->assertSame(144, $secondRule['daily_cap']);
        $this->assertFalse($secondRule['convertible']);
        $this->assertSame(444, $secondSilver['minimum_points']);
    }
}
