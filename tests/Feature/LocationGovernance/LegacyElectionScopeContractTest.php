<?php

namespace Tests\Feature\LocationGovernance;

use App\Models\Group;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Tests\TestCase;

class LegacyElectionScopeContractTest extends TestCase
{
    public function test_elections_are_currently_attached_to_groups(): void
    {
        $group = new Group();

        $this->assertInstanceOf(HasMany::class, $group->elections());
        $this->assertSame('group_id', $group->elections()->getForeignKeyName());
    }
}
