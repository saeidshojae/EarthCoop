<?php

namespace Tests\Unit\LocationGovernance;

use App\Models\GovernanceArea;
use App\Models\GovernanceAreaOverride;
use App\Models\GovernanceCapabilityPolicy;
use App\Services\LocationGovernance\GovernanceCapabilityResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GovernanceCapabilityResolverTest extends TestCase
{
    use RefreshDatabase;

    public function test_capabilities_inherit_from_default_then_country_type_policy_then_area_override(): void
    {
        $area = GovernanceArea::factory()->official()->create([
            'country_code' => 'IR',
            'governance_type' => 'local',
        ]);

        GovernanceCapabilityPolicy::create([
            'scope' => 'default',
            'capabilities' => [
                'public_assembly' => true,
                'chat' => true,
                'secretariat' => false,
                'polls' => true,
                'projects' => true,
                'internal_elections' => true,
                'systemic_elections' => false,
                'managers_inspectors' => false,
                'delegation' => false,
                'official_upstream_participation' => false,
                'group_creation_mode' => 'on_demand',
            ],
        ]);

        GovernanceCapabilityPolicy::create([
            'scope' => 'country_type',
            'country_code' => 'IR',
            'governance_type' => 'local',
            'capabilities' => [
                'secretariat' => true,
                'systemic_elections' => true,
                'managers_inspectors' => true,
                'official_upstream_participation' => true,
                'group_creation_mode' => 'automatic',
            ],
        ]);

        GovernanceAreaOverride::create([
            'governance_area_id' => $area->id,
            'capabilities' => [
                'chat' => false,
                'delegation' => true,
                'group_creation_mode' => 'threshold',
            ],
        ]);

        $caps = app(GovernanceCapabilityResolver::class)->capabilities($area);

        $this->assertTrue($caps->enabled('public_assembly'));
        $this->assertFalse($caps->enabled('chat'));
        $this->assertTrue($caps->enabled('secretariat'));
        $this->assertTrue($caps->enabled('systemic_elections'));
        $this->assertTrue($caps->enabled('managers_inspectors'));
        $this->assertTrue($caps->enabled('delegation'));
        $this->assertTrue($caps->enabled('official_upstream_participation'));
        $this->assertSame('threshold', $caps->groupCreationMode());
    }

    public function test_unrelated_country_or_type_policy_does_not_leak_into_area(): void
    {
        $area = GovernanceArea::factory()->official()->create([
            'country_code' => 'IR',
            'governance_type' => 'local',
        ]);

        GovernanceCapabilityPolicy::create([
            'scope' => 'default',
            'capabilities' => ['chat' => true, 'systemic_elections' => false],
        ]);

        GovernanceCapabilityPolicy::create([
            'scope' => 'country_type',
            'country_code' => 'DE',
            'governance_type' => 'local',
            'capabilities' => ['chat' => false, 'systemic_elections' => true],
        ]);

        $caps = app(GovernanceCapabilityResolver::class)->capabilities($area);

        $this->assertTrue($caps->enabled('chat'));
        $this->assertFalse($caps->enabled('systemic_elections'));
    }

    public function test_materialization_mode_is_restricted_to_approved_catalogue(): void
    {
        $area = GovernanceArea::factory()->official()->create();

        GovernanceCapabilityPolicy::create([
            'scope' => 'default',
            'capabilities' => ['group_creation_mode' => 'invented-mode'],
        ]);

        $this->expectException(\InvalidArgumentException::class);

        app(GovernanceCapabilityResolver::class)->capabilities($area);
    }
}
