<?php

namespace Tests\Feature\LocationGovernance;

use App\Services\LocationGovernance\Import\ReferenceGeographyImporter;
use App\Services\LocationGovernance\Import\ReferenceGovernanceTopologyImporter;
use Database\Seeders\LocationGovernanceBootstrapSeeder;
use Database\Seeders\StageCCanonicalGroupPolicySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ProductionReadinessCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_readiness_fails_closed_when_release_evidence_is_missing(): void
    {
        $this->seed(LocationGovernanceBootstrapSeeder::class);

        $this->artisan('location-governance:readiness')
            ->expectsOutputToContain('NOT READY')
            ->assertExitCode(1);
    }

    public function test_readiness_passes_with_migrations_reference_mapping_flags_and_release_evidence(): void
    {
        $this->seed(LocationGovernanceBootstrapSeeder::class);
        $this->createReferenceAndGovernanceEvidence();

        config()->set('location-governance.validation_sha', '7ea742e7a4b8157de70029007bbdb4edd1409e94');
        config()->set('location-governance.uat_evidence', 'Full Validation #2414 / run 34605952857');

        $this->assertDatabaseCount('user_location_relationships', 0);

        $before = [
            'locations' => DB::table('locations')->count(),
            'governance_areas' => DB::table('governance_areas')->count(),
            'imports' => DB::table('location_import_runs')->count(),
            'proposals' => DB::table('location_proposals')->count(),
            'user_location_relationships' => DB::table('user_location_relationships')->count(),
        ];

        $this->artisan('location-governance:readiness')
            ->expectsOutputToContain('READY')
            ->assertSuccessful();

        $this->assertSame($before, [
            'locations' => DB::table('locations')->count(),
            'governance_areas' => DB::table('governance_areas')->count(),
            'imports' => DB::table('location_import_runs')->count(),
            'proposals' => DB::table('location_proposals')->count(),
            'user_location_relationships' => DB::table('user_location_relationships')->count(),
        ]);
    }

    public function test_readiness_fails_when_latest_reference_import_has_conflicts(): void
    {
        $this->seed(LocationGovernanceBootstrapSeeder::class);
        $this->createReferenceAndGovernanceEvidence(conflicts: 1);

        config()->set('location-governance.validation_sha', '7ea742e7a4b8157de70029007bbdb4edd1409e94');
        config()->set('location-governance.uat_evidence', 'Full Validation #2414 / run 34605952857');

        $this->artisan('location-governance:readiness')
            ->expectsOutputToContain('reference import conflicts')
            ->assertExitCode(1);
    }

    public function test_readiness_fails_when_target_reference_root_has_no_active_schema_valid_child(): void
    {
        $this->seed(LocationGovernanceBootstrapSeeder::class);
        $this->createReferenceAndGovernanceEvidence();

        config()->set('location-governance.validation_sha', '7ea742e7a4b8157de70029007bbdb4edd1409e94');
        config()->set('location-governance.uat_evidence', 'Full Validation #2414 / run 34605952857');

        $rootLocationId = DB::table('location_external_ids')
            ->where('source', ReferenceGeographyImporter::SOURCE)
            ->where('dataset_version', 'v1')
            ->where('external_id', 'IR-COUNTRY')
            ->value('location_id');

        $this->assertNotNull($rootLocationId);

        DB::table('locations')
            ->where('parent_id', $rootLocationId)
            ->update(['status' => 'inactive']);

        $this->artisan('location-governance:readiness')
            ->expectsOutputToContain('reference traversal')
            ->assertExitCode(1);
    }

    private function createReferenceAndGovernanceEvidence(int $conflicts = 0): void
    {
        app(ReferenceGeographyImporter::class)->import('IR', 'v1', true);
        app(ReferenceGovernanceTopologyImporter::class)->apply('IR', 'v1');
        $this->seed(StageCCanonicalGroupPolicySeeder::class);

        if ($conflicts > 0) {
            DB::table('location_import_runs')
                ->where('country_code', 'IR')
                ->where('source', ReferenceGeographyImporter::SOURCE)
                ->where('dataset_version', 'v1')
                ->where('mode', 'apply')
                ->latest('id')
                ->limit(1)
                ->update(['conflicts' => $conflicts]);
        }
    }
}
