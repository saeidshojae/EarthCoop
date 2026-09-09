<?php

namespace Tests\Feature\LocationGovernance;

use App\Models\Location;
use App\Models\LocationExternalId;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class ReferenceGeographyImportIdempotencyTest extends TestCase
{
    use RefreshDatabase;

    public function test_same_country_and_version_can_be_applied_twice_without_duplicate_locations_or_external_ids(): void
    {
        $firstExit = Artisan::call('location:reference-import', [
            'country' => 'IR',
            '--dataset-version' => 'v1',
            '--apply' => true,
        ]);

        $this->assertSame(0, $firstExit);

        $locationCount = Location::where('country_code', 'IR')->count();
        $externalIdCount = LocationExternalId::where('source', 'earthcoop-reference')->where('dataset_version', 'v1')->count();

        $this->assertGreaterThan(0, $locationCount);
        $this->assertGreaterThan(0, $externalIdCount);

        $secondExit = Artisan::call('location:reference-import', [
            'country' => 'IR',
            '--dataset-version' => 'v1',
            '--apply' => true,
        ]);

        $this->assertSame(0, $secondExit);
        $this->assertSame($locationCount, Location::where('country_code', 'IR')->count());
        $this->assertSame(
            $externalIdCount,
            LocationExternalId::where('source', 'earthcoop-reference')->where('dataset_version', 'v1')->count()
        );

        $sari = LocationExternalId::query()
            ->where('source', 'earthcoop-reference')
            ->where('dataset_version', 'v1')
            ->where('external_id', 'IR-SARI-001')
            ->firstOrFail()
            ->location;

        $this->assertSame('Sari', $sari->canonical_name);
        $this->assertSame('city', $sari->type->key);
    }
}
