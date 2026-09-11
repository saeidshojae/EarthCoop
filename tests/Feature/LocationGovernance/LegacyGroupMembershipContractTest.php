<?php

namespace Tests\Feature\LocationGovernance;

use Tests\TestCase;

class LegacyGroupMembershipContractTest extends TestCase
{
    public function test_group_service_currently_keys_spatial_groups_by_level_and_address_id(): void
    {
        $source = file_get_contents(app_path('Services/GroupService.php'));

        $this->assertStringContainsString("where('location_level'", $source);
        $this->assertStringContainsString("where('address_id'", $source);
    }

    public function test_group_model_currently_exposes_legacy_spatial_fields(): void
    {
        $source = file_get_contents(app_path('Models/Group.php'));

        $this->assertStringContainsString("'location_level'", $source);
        $this->assertStringContainsString("'address_id'", $source);
        $this->assertStringContainsString('function address()', $source);
    }
}
