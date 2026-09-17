<?php

namespace Tests\Feature\LocationGovernance;

use App\Models\LocationExternalId;
use App\Services\LocationGovernance\Import\ReferenceGeographyImporter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReferenceLocationSelectionProductionRegressionTest extends TestCase
{
    use RefreshDatabase;

    public function test_real_iran_reference_root_traverses_to_mazandaran_and_sari_county_in_persian(): void
    {
        config(['location-governance.runtime_enabled' => true]);
        app()->setLocale('fa');

        app(ReferenceGeographyImporter::class)->import('IR', 'v1', true);

        $iranIdentity = LocationExternalId::query()
            ->with('location')
            ->where('source', ReferenceGeographyImporter::SOURCE)
            ->where('dataset_version', 'v1')
            ->where('external_id', 'IR-COUNTRY')
            ->firstOrFail();

        $iran = $iranIdentity->location;
        $this->assertNotNull($iran);
        $this->assertSame('active', $iran->status);

        $provinceResponse = $this->getJson('/location/options/'.$iran->id.'/children');
        $provinceResponse->assertOk();

        $mazandaran = collect($provinceResponse->json('data'))
            ->first(fn (array $row): bool => ($row['label'] ?? null) === 'مازندران');

        $this->assertNotNull($mazandaran, 'The imported Iran root must expose Mazandaran in Persian.');
        $this->assertSame('province', $mazandaran['type_key'] ?? null);

        $countyResponse = $this->getJson('/location/options/'.$mazandaran['id'].'/children');
        $countyResponse->assertOk();

        $this->assertTrue(
            collect($countyResponse->json('data'))->contains(
                fn (array $row): bool => ($row['label'] ?? null) === 'شهرستان ساری'
                    && ($row['type_key'] ?? null) === 'county'
            ),
            'The imported Mazandaran branch must expose Sari County in Persian.'
        );
    }
}
