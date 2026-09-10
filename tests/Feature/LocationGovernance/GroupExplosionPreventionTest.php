<?php

namespace Tests\Feature\LocationGovernance;

use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class GroupExplosionPreventionTest extends TestCase
{
    #[DataProvider('suppressedModes')]
    public function test_non_automatic_modes_are_explicit_suppression_reasons(string $mode): void
    {
        $path = app_path('Services/Membership/MembershipEngine.php');
        $this->assertFileExists($path);
        $source = file_get_contents($path);

        $this->assertStringContainsString($mode, $source, "{$mode} must be handled explicitly by MembershipEngine.");
    }

    public function test_membership_engine_does_not_materialize_legacy_groups_or_cartesian_geography_levels(): void
    {
        $path = app_path('Services/Membership/MembershipEngine.php');
        $this->assertFileExists($path);
        $source = file_get_contents($path);

        foreach (['address_id', 'location_level', 'province_id', 'county_id', 'city_id', 'neighborhood_id', 'alley_id'] as $legacyKey) {
            $this->assertStringNotContainsString($legacyKey, $source);
        }

        $this->assertStringNotContainsString('Group::create', $source);
        $this->assertStringNotContainsString('Group::firstOrCreate', $source);
    }

    public static function suppressedModes(): array
    {
        return [
            ['threshold'],
            ['on_demand'],
            ['disabled'],
        ];
    }
}
