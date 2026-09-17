<?php

namespace Tests\Feature\LocationGovernance;

use Illuminate\Support\Str;
use Tests\TestCase;

class LocationProposalChainMigrationContractTest extends TestCase
{
    public function test_chain_migration_rollback_is_non_destructive_and_restores_original_parent_nullability(): void
    {
        $source = file_get_contents(database_path('migrations/2026_09_16_200000_enable_location_proposal_chains.php'));

        $this->assertIsString($source);

        $down = Str::after($source, 'public function down(): void');

        $guard = "whereNotNull('parent_location_proposal_id')";
        $drop = "dropConstrainedForeignId('parent_location_proposal_id')";
        $restore = "\$table->foreignId('parent_location_id')->nullable(false)->change();";

        $this->assertStringContainsString($guard, $down);
        $this->assertStringContainsString('throw new RuntimeException', $down);
        $this->assertStringContainsString($drop, $down);
        $this->assertStringContainsString($restore, $down);

        $guardPosition = strpos($down, $guard);
        $dropPosition = strpos($down, $drop);

        $this->assertIsInt($guardPosition);
        $this->assertIsInt($dropPosition);
        $this->assertLessThan($dropPosition, $guardPosition, 'Rollback safety guard must run before the proposal-parent column is dropped.');
    }
}
