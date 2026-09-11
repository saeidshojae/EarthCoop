<?php

namespace Tests\Feature\LocationGovernance;

use Tests\TestCase;

class MySqlStorageEngineContractTest extends TestCase
{
    public function test_mysql_schema_creation_is_pinned_to_innodb_for_production_portability(): void
    {
        $this->assertSame('InnoDB', config('database.connections.mysql.engine'));
    }
}
