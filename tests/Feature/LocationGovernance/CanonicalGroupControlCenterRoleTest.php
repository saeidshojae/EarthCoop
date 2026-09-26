<?php

namespace Tests\Feature\LocationGovernance;

use Tests\TestCase;

final class CanonicalGroupControlCenterRoleTest extends TestCase
{
    public function test_group_control_center_resolves_member_roles_through_shared_runtime_contract(): void
    {
        $panel = file_get_contents(resource_path('views/groups/partials/group_info_panel.blade.php'));

        $this->assertStringContainsString(
            'GroupMembershipRoleResolver::class)->effectiveRole($group2, $member)',
            $panel,
        );
        $this->assertStringNotContainsString(
            '$locationLevel = strtolower(trim((string)($group2->location_level',
            $panel,
        );
    }
}
