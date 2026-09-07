<?php

namespace Tests\Feature\Secretariat;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SecretariatS9PerformanceIndexTest extends TestCase
{
    use RefreshDatabase;

    public function test_targeted_secretariat_read_path_indexes_exist_on_mysql(): void
    {
        $indexes = collect(DB::select('SHOW INDEX FROM secretariat_records'))
            ->groupBy('Key_name')
            ->map(fn ($rows) => $rows->sortBy('Seq_in_index')->pluck('Column_name')->values()->all());

        $this->assertSame(
            ['office_id', 'status', 'updated_at', 'id'],
            $indexes->get('secretariat_records_office_status_updated_idx')
        );
        $this->assertSame(
            ['office_id', 'registered_at', 'id'],
            $indexes->get('secretariat_records_office_registered_idx')
        );
    }

    public function test_existing_high_value_acl_dispatch_and_relation_indexes_remain_present(): void
    {
        $this->assertIndexExists('secretariat_acl_entries', 'secretariat_acl_lookup_idx');
        $this->assertIndexExists('secretariat_dispatches', 'secretariat_dispatches_status_due_idx');
        $this->assertIndexExists('secretariat_dispatches', 'secretariat_dispatches_status_follow_up_idx');
        $this->assertIndexExists('secretariat_relations', 'secretariat_relations_source_type_idx');
        $this->assertIndexExists('secretariat_relations', 'secretariat_relations_target_type_idx');
    }

    private function assertIndexExists(string $table, string $index): void
    {
        $names = collect(DB::select("SHOW INDEX FROM {$table}"))->pluck('Key_name')->all();
        $this->assertContains($index, $names, "Missing expected index {$index} on {$table}");
    }
}
