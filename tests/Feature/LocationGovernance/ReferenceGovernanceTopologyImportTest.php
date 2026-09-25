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

        $this->assertSame(15, GovernanceArea::query()->count());
        $this->assertSame(13, DB::table('governance_area_locations')->count());

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

        $urbanRegionWithoutNeighborhood = LocationExternalId::query()
            ->where('source', 'earthcoop-reference')
            ->where('dataset_version', 'v1')
            ->where('external_id', 'IR-SARI-URBAN-NONEIGHBORHOOD')
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
        $urbanRegionWithoutNeighborhoodArea = $resolver->baseOfficialAreaForResidence($urbanRegionWithoutNeighborhood);
        $ruralArea = $resolver->baseOfficialAreaForResidence($ruralResidence);

        $this->assertNotNull($urbanRegionWithoutNeighborhoodArea);
        $this->assertSame('urban_region', $urbanRegionWithoutNeighborhoodArea->governance_type);
        $this->assertSame('ir-reference-v1-sari', $urbanRegionWithoutNeighborhoodArea->parent?->key);

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
        $this->assertStringContainsString('unchanged: 15', $output);

        // The reference Location and its official GovernanceArea represent one
        // physical village, with one stable mapping and one localized name.
        $this->assertSame('Reference Village Without Neighborhood', $ruralResidence->canonical_name);
        $this->assertSame('Reference Village Without Neighborhood', $ruralArea->canonical_name);
        $this->assertSame('روستای مرجع بدون محله', $ruralResidence->localized_names['fa']);
        $this->assertSame($ruralResidence->localized_names['fa'], $ruralArea->localized_names['fa']);

        // Simulate the older deployed reference name. A localized-name-only
        // mismatch must be visible in dry-run, then corrected without changing
        // the location, area identity, mappings, or any other reference row.
        $stableAreaId = $ruralArea->id;
        $stableLocationId = $ruralResidence->id;
        $ruralArea->forceFill(['localized_names' => [
            'fa' => 'روستای چهاردانگه بدون محله',
            'en' => 'Reference Village Without Neighborhood',
        ]])->save();

        $this->assertSame(0, Artisan::call('location-governance:reference-topology', [
            'country' => 'IR', '--dataset-version' => 'v1', '--dry-run' => true,
        ]));
        $this->assertStringContainsString('update: 1', Artisan::output());
        $this->assertSame('روستای چهاردانگه بدون محله', $ruralArea->fresh()->localized_names['fa']);

        $this->assertSame(0, Artisan::call('location-governance:reference-topology', [
            'country' => 'IR', '--dataset-version' => 'v1', '--apply' => true,
        ]));
        $this->assertSame($stableAreaId, $ruralArea->fresh()->id);
        $this->assertSame($stableLocationId, $ruralResidence->fresh()->id);
        $this->assertSame('روستای مرجع بدون محله', $ruralArea->fresh()->localized_names['fa']);
        $this->assertSame([$stableLocationId], $ruralArea->locations()->pluck('locations.id')->all());
        $this->assertSame(15, GovernanceArea::query()->count());

        $this->assertSame(0, Artisan::call('location-governance:reference-topology', [
            'country' => 'IR', '--dataset-version' => 'v1', '--dry-run' => true,
        ]));
        $this->assertStringContainsString('update: 0', Artisan::output());
    }

    public function test_iran_1404_v2_builds_complete_authoritative_admin_governance_alongside_v1(): void
    {
        $this->seed(LocationGovernanceBootstrapSeeder::class);

        $this->assertSame(0, Artisan::call('location:reference-import', [
            'country' => 'IR', '--dataset-version' => 'v1', '--apply' => true,
        ]));
        $this->assertSame(0, Artisan::call('location-governance:reference-topology', [
            'country' => 'IR', '--dataset-version' => 'v1', '--apply' => true,
        ]));

        $this->assertSame(0, Artisan::call('location:reference-import', [
            'country' => 'IR',
            '--dataset-version' => 'v2',
            '--apply' => true,
            '--confirm' => 'APPLY-IR-1404-V2-UAT',
        ]), Artisan::output());

        $this->assertSame(0, Artisan::call('location-governance:reference-topology', [
            'country' => 'IR', '--dataset-version' => 'v2', '--dry-run' => true,
        ]));
        $topologyDryRunOutput = Artisan::output();
        $this->assertStringContainsString('create: 6158', $topologyDryRunOutput);
        $this->assertStringContainsString('conflict: 0', $topologyDryRunOutput);

        $this->assertSame(0, Artisan::call('location-governance:reference-topology', [
            'country' => 'IR',
            '--dataset-version' => 'v2',
            '--apply' => true,
            '--confirm' => 'APPLY-GOV-IR-1404-V2-UAT',
        ]), Artisan::output());

        $this->assertSame(
            6158,
            GovernanceArea::query()->where('key', 'like', 'ir-reference-v2-ir-1404-%')->count(),
        );

        $region = LocationExternalId::query()
            ->where('source', 'earthcoop-reference')
            ->where('dataset_version', 'v2')
            ->where('external_id', 'IR-1404-5984')
            ->firstOrFail()
            ->location;
        $this->assertSame('urban_region', $region->type?->key);

        $chain = app(GovernanceResolver::class)->officialAreasForResidence($region);
        $this->assertSame('ir-reference-v2-ir-1404-5984', $chain->first()?->key);
        $this->assertContains('ir-reference-v2-ir-1404-4602', $chain->pluck('key')->all());
        $this->assertContains('ir-reference-v2-ir-1404-1', $chain->pluck('key')->all());
        $this->assertSame('earthcoop-global', $chain->last()?->key);

        $this->assertSame(0, Artisan::call('location-governance:reference-topology', [
            'country' => 'IR', '--dataset-version' => 'v2', '--dry-run' => true,
        ]));
        $topologyRepeatOutput = Artisan::output();
        $this->assertStringContainsString('create: 0', $topologyRepeatOutput);
        $this->assertStringContainsString('update: 0', $topologyRepeatOutput);
        $this->assertStringContainsString('conflict: 0', $topologyRepeatOutput);
        $this->assertStringContainsString('unchanged: 6160', $topologyRepeatOutput);
    }

}
