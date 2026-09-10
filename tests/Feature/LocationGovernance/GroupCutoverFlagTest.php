<?php

namespace Tests\Feature\LocationGovernance;

use Tests\TestCase;

class GroupCutoverFlagTest extends TestCase
{
    public function test_groups_cutover_flag_defaults_off(): void
    {
        $config = require config_path('location-governance.php');

        $this->assertArrayHasKey('groups_enabled', $config);
        $this->assertFalse($config['groups_enabled']);
    }

    public function test_group_service_delegates_to_canonical_materialization_only_behind_flag(): void
    {
        $path = app_path('Services/GroupService.php');
        $source = file_get_contents($path);

        $this->assertStringContainsString('location-governance.groups_enabled', $source);
        $this->assertStringContainsString('GovernanceScopedGroupService', $source);
        $this->assertStringContainsString('MembershipEngine', $source);
    }

    public function test_canonical_group_path_does_not_make_fixed_geography_levels_authoritative(): void
    {
        $path = app_path('Services/Groups/GovernanceScopedGroupService.php');
        $this->assertFileExists($path);
        $source = file_get_contents($path);

        foreach (['address_id', 'location_level', "'alley'", "'street'", "'neighborhood'"] as $legacyAuthority) {
            $this->assertStringNotContainsString($legacyAuthority, $source);
        }

        foreach (['governance_area_id', 'dimension_key', 'dimension_value_key'] as $canonicalColumn) {
            $this->assertStringContainsString($canonicalColumn, $source);
        }
    }
}
