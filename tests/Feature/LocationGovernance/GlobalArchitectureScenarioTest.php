<?php

namespace Tests\Feature\LocationGovernance;

use App\Models\LocationSchema;
use Database\Seeders\LocationGovernanceBootstrapSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\LocationGovernance\LocationFixture;
use Tests\TestCase;

class GlobalArchitectureScenarioTest extends TestCase
{
    use RefreshDatabase;

    public function test_fresh_bootstrap_supports_urban_and_rural_variable_depth_paths(): void
    {
        $this->seed(LocationGovernanceBootstrapSeeder::class);

        $schema = LocationSchema::query()
            ->with(['types', 'typeRelations.parentType', 'typeRelations.childType'])
            ->where('key', 'ir-reference-v1')
            ->firstOrFail();

        $urban = LocationFixture::createPath(
            $schema,
            ['country', 'province', 'county', 'section', 'city', 'urban_region', 'neighborhood', 'street'],
            ['Iran', 'Mazandaran', 'Sari County', 'Central Section', 'Sari', 'Region 1', 'Sample Neighborhood', 'Sample Street'],
        );

        $rural = LocationFixture::createPath(
            $schema,
            ['country', 'province', 'county', 'section', 'rural_district', 'village'],
            ['Iran', 'Mazandaran', 'Sari County', 'Chahardangeh', 'Poshtkuh', 'Sample Village'],
        );

        $this->assertSame('street', $urban->last()->type->key);
        $this->assertTrue($urban->last()->type->is_residence_endpoint);
        $this->assertSame('village', $rural->last()->type->key);
        $this->assertTrue($rural->last()->type->is_residence_endpoint);
        $this->assertFalse($rural->last()->children()->exists(), 'A village must remain valid without a neighborhood child.');
    }

    public function test_micro_location_schema_allows_community_eligible_complex_and_building_without_forcing_them(): void
    {
        $this->seed(LocationGovernanceBootstrapSeeder::class);

        $schema = LocationSchema::query()
            ->with(['types', 'typeRelations.parentType', 'typeRelations.childType'])
            ->where('key', 'ir-reference-v1')
            ->firstOrFail();

        $streetChildren = LocationFixture::allowedChildTypeKeys($schema, 'street')->sort()->values()->all();
        $alleyChildren = LocationFixture::allowedChildTypeKeys($schema, 'alley')->sort()->values()->all();
        $complexChildren = LocationFixture::allowedChildTypeKeys($schema, 'complex')->sort()->values()->all();

        $this->assertSame(['alley', 'complex'], $streetChildren);
        $this->assertSame(['complex'], $alleyChildren);
        $this->assertSame(['building'], $complexChildren);
    }

    public function test_safety_sensitive_global_scenarios_remain_permanent_regression_contracts(): void
    {
        $contracts = [
            'RegistrationPrimaryResidenceTest.php',
            'LocationProposalWorkflowTest.php',
            'DistinctVerifierThresholdTest.php',
            'GeolocationAssistTest.php',
            'ProfileResidenceTransferTest.php',
            'NonResidenceRelationshipVotingTest.php',
            'MultidimensionalMembershipTest.php',
            'GroupExplosionPreventionTest.php',
            'OfficialElectionGovernanceTopologyTest.php',
            'CommunityElectionBoundaryTest.php',
            'LocationLifecycleHistoryTest.php',
        ];

        foreach ($contracts as $contract) {
            $path = base_path('tests/Feature/LocationGovernance/'.$contract);
            $this->assertFileExists($path, "Permanent architecture regression [{$contract}] must not be removed.");
        }

        $this->assertStringContainsString(
            'same verifier',
            strtolower(file_get_contents(base_path('tests/Feature/LocationGovernance/DistinctVerifierThresholdTest.php'))),
        );
        $this->assertStringContainsString(
            'work',
            strtolower(file_get_contents(base_path('tests/Feature/LocationGovernance/NonResidenceRelationshipVotingTest.php'))),
        );
        $this->assertStringContainsString(
            'split',
            strtolower(file_get_contents(base_path('tests/Feature/LocationGovernance/LocationLifecycleHistoryTest.php'))),
        );
    }
}
