<?php

namespace Tests\Feature\LocationGovernance;

use Illuminate\Support\Str;
use Tests\TestCase;

final class ReferenceSettlementMigrationRollbackContractTest extends TestCase
{
    public function test_pending_residence_migration_rolls_back_only_when_no_settlement_intents_exist(): void
    {
        $source = file_get_contents(database_path(
            'migrations/2026_09_24_000004_allow_reference_settlement_claim_pending_intents.php'
        ));

        $this->assertIsString($source);
        $down = Str::after($source, 'public function down(): void');

        $guard = "whereNotNull('reference_settlement_residence_claim_id')";
        $drop = "dropColumn('reference_settlement_residence_claim_id')";
        $restore = "\$table->unsignedBigInteger('location_proposal_id')->nullable(false)->change();";

        $this->assertStringContainsString($guard, $down);
        $this->assertStringContainsString('throw new RuntimeException', $down);
        $this->assertStringContainsString($drop, $down);
        $this->assertStringContainsString($restore, $down);

        $guardPosition = strpos($down, $guard);
        $dropPosition = strpos($down, $drop);
        $restorePosition = strpos($down, $restore);

        $this->assertIsInt($guardPosition);
        $this->assertIsInt($dropPosition);
        $this->assertIsInt($restorePosition);
        $this->assertLessThan($dropPosition, $guardPosition);
        $this->assertLessThan($restorePosition, $guardPosition);
    }

    public function test_pending_group_migration_rolls_back_only_when_no_settlement_group_shells_exist(): void
    {
        $source = file_get_contents(database_path(
            'migrations/2026_09_24_000005_link_pending_group_requests_to_reference_settlement_claims.php'
        ));

        $this->assertIsString($source);
        $down = Str::after($source, 'public function down(): void');

        $guard = "whereNotNull('reference_settlement_residence_claim_id')";
        $drop = "dropColumn('reference_settlement_residence_claim_id')";

        $this->assertStringContainsString($guard, $down);
        $this->assertStringContainsString('throw new RuntimeException', $down);
        $this->assertStringContainsString($drop, $down);

        $guardPosition = strpos($down, $guard);
        $dropPosition = strpos($down, $drop);

        $this->assertIsInt($guardPosition);
        $this->assertIsInt($dropPosition);
        $this->assertLessThan($dropPosition, $guardPosition);
    }
}
