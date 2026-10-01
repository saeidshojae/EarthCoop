<?php

namespace Tests\Feature\Communication;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class CommunicationQueueInfrastructureTest extends TestCase
{
    use RefreshDatabase;

    public function test_database_queue_jobs_table_exists_with_required_columns(): void
    {
        $this->assertTrue(Schema::hasTable('jobs'));

        foreach ([
            'id',
            'queue',
            'payload',
            'attempts',
            'reserved_at',
            'available_at',
            'created_at',
        ] as $column) {
            $this->assertTrue(
                Schema::hasColumn('jobs', $column),
                "Missing required queue column: {$column}"
            );
        }
    }
}
