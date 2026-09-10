<?php

namespace Tests\Feature\LocationGovernance;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MultidimensionalMembershipTest extends TestCase
{
    use RefreshDatabase;

    public function test_membership_engine_source_is_composed_from_governance_and_all_five_dimension_resolvers(): void
    {
        $path = app_path('Services/Membership/MembershipEngine.php');
        $this->assertFileExists($path);
        $source = file_get_contents($path);

        foreach ([
            'GovernanceResolver',
            'PublicDimensionResolver',
            'ProfessionDimensionResolver',
            'SpecialtyDimensionResolver',
            'AgeDimensionResolver',
            'GenderDimensionResolver',
        ] as $dependency) {
            $this->assertStringContainsString($dependency, $source, "MembershipEngine must compose {$dependency}.");
        }

        $this->assertStringContainsString('primary_residence', $source);
        $this->assertStringContainsString('ended_at', $source);
    }

    public function test_work_and_study_relationships_are_not_used_as_official_governance_inputs(): void
    {
        $path = app_path('Services/Membership/MembershipEngine.php');
        $this->assertFileExists($path);
        $source = file_get_contents($path);

        $this->assertStringNotContainsString("relationship_type', 'work'", $source);
        $this->assertStringNotContainsString("relationship_type', 'study'", $source);
    }
}
