<?php

namespace Tests\Feature\LocationGovernance;

use App\Enums\Membership\GroupCreationMode;
use App\Models\Group;
use App\Services\Membership\MembershipEngine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\LocationGovernance\MembershipFixture;
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

    public function test_identical_inputs_produce_identical_fingerprint_and_all_five_dimension_intents(): void
    {
        ['user' => $user, 'area' => $area] = MembershipFixture::canonicalUser();
        $engine = app(MembershipEngine::class);

        $beforeGroups = Group::count();
        $first = $engine->resolve($user);
        $second = $engine->resolve($user->fresh());

        $this->assertSame($first->auditFingerprint, $second->auditFingerprint);
        $this->assertCount(5, $first->materializableIntents);
        $this->assertCount(0, $first->suppressedIntents);
        $this->assertSame($beforeGroups, Group::count());
        $this->assertSame([$area->id], $first->officialGovernanceAreas->pluck('id')->all());
        $this->assertSame(
            ['age', 'gender', 'profession', 'public', 'specialty'],
            $first->materializableIntents->pluck('dimensionKey')->sort()->values()->all(),
        );
    }

    public function test_non_automatic_policy_is_suppressed_without_creating_a_group(): void
    {
        ['user' => $user] = MembershipFixture::canonicalUser([
            'profession' => GroupCreationMode::Threshold,
            'specialty' => GroupCreationMode::OnDemand,
            'gender' => GroupCreationMode::Disabled,
        ]);

        $beforeGroups = Group::count();
        $resolution = app(MembershipEngine::class)->resolve($user, materialize: true);

        $this->assertCount(2, $resolution->materializableIntents);
        $this->assertCount(3, $resolution->suppressedIntents);
        $this->assertSame(
            ['disabled', 'on_demand', 'threshold'],
            $resolution->suppressedIntents->pluck('suppressionReason')->sort()->values()->all(),
        );
        $this->assertSame($beforeGroups, Group::count());
    }
}
