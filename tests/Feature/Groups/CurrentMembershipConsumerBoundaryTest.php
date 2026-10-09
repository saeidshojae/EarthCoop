<?php

namespace Tests\Feature\Groups;

use PHPUnit\Framework\TestCase;

/**
 * RED checkpoint: current-membership consumers must not trust historical
 * group_user rows. These source-level tripwires supplement behavioral tests
 * added in the implementation phase.
 */
final class CurrentMembershipConsumerBoundaryTest extends TestCase
{
    public function test_najm_hoda_group_delegation_does_not_use_unscoped_group_relation(): void
    {
        $source = file_get_contents(dirname(__DIR__, 3).'/app/Services/NajmHoda/Runtime/NajmHodaDelegatedPermissionService.php');
        $this->assertIsString($source);
        $this->assertStringNotContainsString("->groups()->where('groups.id', (int) \$id)->exists()", $source);
    }

    public function test_najm_hoda_knowledge_graph_does_not_use_unscoped_group_relation(): void
    {
        $source = file_get_contents(dirname(__DIR__, 3).'/app/Services/NajmHoda/Runtime/NajmHodaUnifiedDomainKnowledgeGraphService.php');
        $this->assertIsString($source);
        $this->assertStringNotContainsString("->groups()->where('groups.id', \$groupId)->exists()", $source);
        $this->assertStringNotContainsString("->groups()->pluck('groups.id')", $source);
        $this->assertStringNotContainsString("->groups()->latest('groups.id')", $source);
    }

    public function test_managed_group_member_count_ignores_inactive_rows(): void
    {
        $source = file_get_contents(dirname(__DIR__, 3).'/resources/views/groups/partials/table-managed.blade.php');
        $this->assertIsString($source);
        $this->assertStringContainsString("->where('status', 1)", $source);
    }
}
