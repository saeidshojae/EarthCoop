<?php

namespace Tests\Unit\Membership;

use App\Enums\Membership\GroupCreationMode;
use App\Models\GroupCreationPolicy;
use App\Models\MembershipDimension;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class GroupCreationPolicyTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function group_creation_mode_is_exhaustively_limited_to_the_four_approved_modes(): void
    {
        $this->assertTrue(enum_exists(GroupCreationMode::class), 'GroupCreationMode enum must exist.');

        $this->assertSame(
            ['automatic', 'threshold', 'on_demand', 'disabled'],
            array_map(static fn (GroupCreationMode $mode): string => $mode->value, GroupCreationMode::cases()),
        );
    }

    #[Test]
    public function membership_dimensions_are_explicit_domain_records_not_arbitrary_geography_keys(): void
    {
        $this->assertTrue(class_exists(MembershipDimension::class), 'MembershipDimension model must exist.');

        foreach (['public', 'profession', 'specialty', 'age', 'gender'] as $key) {
            MembershipDimension::query()->create([
                'key' => $key,
                'name' => ucfirst($key),
                'resolver_class' => 'App\\Services\\Membership\\'.ucfirst($key).'DimensionResolver',
                'enabled' => true,
            ]);
        }

        $this->assertSame(
            ['public', 'profession', 'specialty', 'age', 'gender'],
            MembershipDimension::query()->orderBy('id')->pluck('key')->all(),
        );
    }

    #[Test]
    public function creation_policy_can_express_each_mode_and_an_optional_threshold_without_materializing_groups(): void
    {
        $this->assertTrue(class_exists(GroupCreationPolicy::class), 'GroupCreationPolicy model must exist.');

        $dimension = MembershipDimension::query()->create([
            'key' => 'profession',
            'name' => 'Profession',
            'resolver_class' => 'App\\Services\\Membership\\ProfessionDimensionResolver',
            'enabled' => true,
        ]);

        foreach (GroupCreationMode::cases() as $index => $mode) {
            $policy = GroupCreationPolicy::query()->create([
                'membership_dimension_id' => $dimension->id,
                'mode' => $mode,
                'threshold' => $mode === GroupCreationMode::Threshold ? 20 : null,
                'priority' => $index,
            ])->fresh();

            $this->assertSame($mode, $policy->mode);
            $this->assertSame(
                $mode === GroupCreationMode::Threshold ? 20 : null,
                $policy->threshold,
            );
        }
    }

    #[Test]
    public function creation_policy_has_scope_override_slots_without_encoding_fixed_geography_levels(): void
    {
        $migration = file_get_contents(database_path('migrations/2026_09_10_000005_create_membership_dimension_tables.php'));

        $this->assertStringContainsString("'governance_area_id'", $migration);
        $this->assertStringContainsString("'governance_type'", $migration);
        $this->assertStringContainsString("'governance_rank'", $migration);

        foreach (['province_id', 'county_id', 'city_id', 'neighborhood_id', 'street_id', 'alley_id'] as $legacyColumn) {
            $this->assertStringNotContainsString($legacyColumn, $migration);
        }
    }
}
