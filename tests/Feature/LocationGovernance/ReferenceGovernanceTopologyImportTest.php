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

    public function test_reference_topology_apply_maps_complete_urban_and_rural_governance_chains(): void
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

        $this->assertSame(14, GovernanceArea::query()->count());
        $this->assertSame(12, DB::table('governance_area_locations')->count());

        $global = GovernanceArea::query()->where('key', 'earthcoop-global')->firstOrFail();
        $asia = GovernanceArea::query()->where('key', 'earthcoop-continent-asia')->firstOrFail();
        $iran = GovernanceArea::query()->where('key', 'ir-reference-v1-country')->firstOrFail();

        $this->assertNull($global->country_code);
        $this->assertNull($asia->country_code);
        $this->assertSame('IR', $iran->country_code);
        $this->assertNull($global->parent_id);
        $this->assertSame($global->id, $asia->parent_id);
        $this->assertSame($asia->id, $iran->parent_id);

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

        $kiasar = LocationExternalId::query()
            ->where('source', 'earthcoop-reference')
            ->where('dataset_version', 'v1')
            ->where('external_id', 'IR-MAZ-SARI-CHAHARDANGEH-KIASAR')
            ->firstOrFail()
            ->location;

        $resolver = app(GovernanceResolver::class);
        $kiasarArea = $resolver->baseOfficialAreaForResidence($kiasar);
        $this->assertNotNull($kiasarArea);
        $this->assertSame('city', $kiasarArea->governance_type);
        $this->assertSame('ir-reference-v1-chahardangeh-section', $kiasarArea->parent?->key);

        $urbanArea = $resolver->baseOfficialAreaForResidence($urbanResidence);
        $ruralArea = $resolver->baseOfficialAreaForResidence($ruralResidence);

        $this->assertNotNull($urbanArea);
        $this->assertSame('local', $urbanArea->governance_type);
        $this->assertSame(900, $urbanArea->rank);
        $this->assertSame(
            [
                'ir-reference-v1-sari-neighborhood-01',
                'ir-reference-v1-sari-urban-region-01',
                'ir-reference-v1-sari',
                'ir-reference-v1-sari-central-section',
                'ir-reference-v1-sari-county',
                'ir-reference-v1-mazandaran',
                'ir-reference-v1-country',
                'earthcoop-continent-asia',
                'earthcoop-global',
            ],
            $resolver->officialAreasForResidence($urbanResidence)->pluck('key')->all(),
        );

        $this->assertNotNull($ruralArea);
        $this->assertSame('village', $ruralArea->governance_type);
        $this->assertSame(700, $ruralArea->rank);
        $this->assertSame(
            [
                'ir-reference-v1-chahardangeh-village-no-neighborhood',
                'ir-reference-v1-chahardangeh-rural-district',
                'ir-reference-v1-chahardangeh-section',
                'ir-reference-v1-sari-county',
                'ir-reference-v1-mazandaran',
                'ir-reference-v1-country',
                'earthcoop-continent-asia',
                'earthcoop-global',
            ],
            $resolver->officialAreasForResidence($ruralResidence)->pluck('key')->all(),
        );

        $this->assertSame(0, Artisan::call('location-governance:reference-topology', [
            'country' => 'IR',
            '--dataset-version' => 'v1',
            '--dry-run' => true,
        ]));
        $output = Artisan::output();
        $this->assertStringContainsString('create: 0', $output);
        $this->assertStringContainsString('update: 0', $output);
        $this->assertStringContainsString('conflict: 0', $output);
        $this->assertStringContainsString('unchanged: 14', $output);
    }
}
