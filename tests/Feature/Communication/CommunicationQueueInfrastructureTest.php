<?php

namespace Tests\Feature\Communication;

use App\Jobs\Communication\DeliverCommunicationRecipient;
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

    public function test_communication_job_is_persisted_on_named_database_queue(): void
    {
        config()->set('queue.default', 'database');

        DeliverCommunicationRecipient::dispatch(12345)
            ->onQueue('communications-critical');

        $this->assertDatabaseHas('jobs', [
            'queue' => 'communications-critical',
            'attempts' => 0,
        ]);
    }
}
