<?php

namespace Tests\Feature\LocationGovernance;

use Tests\TestCase;

final class Iran1404ProductionCutoverCommandTest extends TestCase
{
    public function test_production_cutover_commands_require_separate_exact_tokens(): void
    {
        $geography = file_get_contents(app_path('Console/Commands/LocationReferenceImportCommand.php'));
        $topology = file_get_contents(app_path('Console/Commands/ReferenceGovernanceTopologyCommand.php'));
        $settlements = file_get_contents(app_path('Console/Commands/Iran1404SettlementCatalogCommand.php'));

        $this->assertStringContainsString("app()->environment('production')", $geography);
        $this->assertStringContainsString('APPLY-IR-1404-V2-PRODUCTION', $geography);
        $this->assertStringContainsString('APPLY-IR-1404-V2-ISOLATED', $geography);
        $this->assertStringContainsString('APPLY-IR-1404-V2-UAT', $geography);

        $this->assertStringContainsString("app()->environment('production')", $topology);
        $this->assertStringContainsString('APPLY-GOV-IR-1404-V2-PRODUCTION', $topology);
        $this->assertStringContainsString('APPLY-GOV-IR-1404-V2-UAT', $topology);

        $this->assertStringContainsString("app()->environment('production')", $settlements);
        $this->assertStringContainsString('APPLY-IR-SETTLEMENT-CATALOG-PRODUCTION', $settlements);
        $this->assertStringContainsString('APPLY-IR-SETTLEMENT-CATALOG-ISOLATED', $settlements);
        $this->assertStringContainsString('importPinnedSource($apply, $allowProduction)', $settlements);
    }

    public function test_pinned_settlement_import_is_bounded_fail_closed_and_non_authorizing(): void
    {
        $source = file_get_contents(app_path('Services/LocationGovernance/Import/IranSettlementCatalogImporter.php'));

        $this->assertStringContainsString("SOURCE_GIT_BLOB = 'ca9f4a0d69c7c9d77e6434447c7fe123a322271e'", $source);
        $this->assertStringContainsString('EXPECTED_COUNT = 99317', $source);
        $this->assertStringContainsString('array_chunk($requiredParents, 500)', $source);
        $this->assertStringContainsString("count($batch) === 500", $source);
        $this->assertStringContainsString('DB::transaction', $source);
        $this->assertStringContainsString("'classification' => 'unverified_settlement'", $source);
        $this->assertStringContainsString("'residential_eligibility' => 'unverified'", $source);
        $this->assertStringContainsString("'governance_authorized' => false", $source);
        $this->assertStringContainsString("'operational_promotion_allowed' => false", $source);
        $this->assertStringContainsString('requires the complete Iran 1404 v2 rural-district geography first', $source);
    }
}
