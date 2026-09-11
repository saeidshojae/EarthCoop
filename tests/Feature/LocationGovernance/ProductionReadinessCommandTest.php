<?php

namespace Tests\Feature\LocationGovernance;

use App\Models\GovernanceArea;
use App\Models\Location;
use App\Models\LocationSchema;
use App\Models\LocationType;
use App\Services\LocationGovernance\Import\ReferenceGeographyImporter;
use Database\Seeders\LocationGovernanceBootstrapSeeder;
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

    private function createReferenceAndGovernanceEvidence(int $conflicts = 0): void
    {
        $schema = LocationSchema::query()->where('key', 'ir-reference-v1')->firstOrFail();
        $countryType = LocationType::query()->where('key', 'country')->firstOrFail();

        $location = Location::factory()->create([
            'location_schema_id' => $schema->id,
            'location_type_id' => $countryType->id,
            'country_code' => 'IR',
        ]);

        $area = GovernanceArea::factory()->official()->create([
            'country_code' => 'IR',
            'governance_type' => 'country',
            'rank' => 1000,
        ]);

        DB::table('governance_area_locations')->insert([
            'governance_area_id' => $area->id,
            'location_id' => $location->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('location_import_runs')->insert([
            'country_code' => 'IR',
            'source' => ReferenceGeographyImporter::SOURCE,
            'dataset_version' => 'v1',
            'mode' => 'apply',
            'status' => 'completed',
            'creates' => 1,
            'updates' => 0,
            'deactivates' => 0,
            'conflicts' => $conflicts,
            'unchanged' => 0,
            'dataset_hash' => str_repeat('a', 64),
            'started_at' => now()->subMinute(),
            'finished_at' => now(),
            'metadata' => json_encode(['test' => true]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
