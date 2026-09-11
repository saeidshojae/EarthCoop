<?php

namespace Tests\Feature\LocationGovernance;

use App\Models\GovernanceArea;
use App\Models\LocationExternalId;
use App\Services\LocationGovernance\GovernanceResolver;
use Database\Seeders\LocationGovernanceBootstrapSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ReferenceGovernanceTopologyImportTest extends TestCase
{
    use RefreshDatabase;

    public function test_reference_topology_apply_maps_urban_and_rural_reference_locations_to_official_governance(): void
    {
        $this->seed(LocationGovernanceBootstrapSeeder::class);

        $this->assertSame(0, Artisan::call('location:reference-import', [
            'country' => 'IR',
            '--dataset-version' => 'v1',
            '--apply' => true,
        ]));

        $this->assertSame(0, Artisan::call('location-governance:reference-topology', [
            'country' => 'IR',
            '--dataset-version' => 'v1',
            '--apply' => true,
        ]));

        $this->assertSame(5, GovernanceArea::query()->count());
        $this->assertSame(5, DB::table('governance_area_locations')->count());

        $urbanResidence = LocationExternalId::query()
            ->where('source', 'earthcoop-reference')
            ->where('dataset_version', 'v1')
            ->where('external_id', 'IR-SARI-NBH-01')
            ->firstOrFail()
            ->location;

        $ruralResidence = LocationExternalId::query()
            ->where('source', 'earthcoop-reference')
            ->where('dataset_version', 'v1')
            ->where('external_id', 'IR-MAZ-SARI-CHAHARDANGEH-VILLAGE-NONEIGHBORHOOD')
            ->firstOrFail()
            ->location;

        $urbanArea = app(GovernanceResolver::class)->baseOfficialAreaForResidence($urbanResidence);
        $ruralArea = app(GovernanceResolver::class)->baseOfficialAreaForResidence($ruralResidence);

        $this->assertNotNull($urbanArea);
        $this->assertSame('local', $urbanArea->governance_type);
        $this->assertSame(900, $urbanArea->rank);

        $this->assertNotNull($ruralArea);
        $this->assertSame('village', $ruralArea->governance_type);
        $this->assertSame(800, $ruralArea->rank);

        $this->assertSame(0, Artisan::call('location-governance:reference-topology', [
            'country' => 'IR',
            '--dataset-version' => 'v1',
            '--dry-run' => true,
        ]));
        $output = Artisan::output();
        $this->assertStringContainsString('create: 0', $output);
        $this->assertStringContainsString('update: 0', $output);
        $this->assertStringContainsString('conflict: 0', $output);
        $this->assertStringContainsString('unchanged: 5', $output);
    }
}
