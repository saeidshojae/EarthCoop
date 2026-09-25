<?php

namespace Tests\Feature\LocationGovernance;

use App\Services\LocationGovernance\Import\IranSettlementCatalogImporter;
use App\Services\LocationGovernance\Import\ReferenceGeographyImporter;
use App\Services\LocationGovernance\Import\ReferenceGovernanceTopologyImporter;
use App\Data\LocationGovernance\ReferenceImportResult;
use Illuminate\Support\Facades\Artisan;
use Mockery;
use Tests\TestCase;

final class Iran1404ProductionCutoverCommandTest extends TestCase
{
    public function test_production_geography_requires_exact_production_token(): void
    {
        $original = $this->app['env'];
        $this->app['env'] = 'production';

        try {
            $importer = Mockery::mock(ReferenceGeographyImporter::class);
            $importer->shouldReceive('import')->once()->with('IR', 'v2', true)
                ->andReturn(new ReferenceImportResult(6158, 0, 0, 0, 0));
            $this->app->instance(ReferenceGeographyImporter::class, $importer);

            $this->assertSame(1, Artisan::call('location:reference-import', [
                'country' => 'IR', '--dataset-version' => 'v2', '--apply' => true,
                '--confirm' => 'APPLY-IR-1404-V2-UAT',
            ]));

            $this->assertSame(0, Artisan::call('location:reference-import', [
                'country' => 'IR', '--dataset-version' => 'v2', '--apply' => true,
                '--confirm' => 'APPLY-IR-1404-V2-PRODUCTION',
            ]));
        } finally {
            $this->app['env'] = $original;
        }
    }

    public function test_production_topology_requires_exact_production_token(): void
    {
        $original = $this->app['env'];
        $this->app['env'] = 'production';

        try {
            $importer = Mockery::mock(ReferenceGovernanceTopologyImporter::class);
            $importer->shouldReceive('apply')->once()->with('IR', 'v2')
                ->andReturn(['create' => 6160, 'update' => 0, 'conflict' => 0, 'unchanged' => 0]);
            $this->app->instance(ReferenceGovernanceTopologyImporter::class, $importer);

            $this->assertSame(1, Artisan::call('location-governance:reference-topology', [
                'country' => 'IR', '--dataset-version' => 'v2', '--apply' => true,
                '--confirm' => 'APPLY-GOV-IR-1404-V2-UAT',
            ]));

            $this->assertSame(0, Artisan::call('location-governance:reference-topology', [
                'country' => 'IR', '--dataset-version' => 'v2', '--apply' => true,
                '--confirm' => 'APPLY-GOV-IR-1404-V2-PRODUCTION',
            ]));
        } finally {
            $this->app['env'] = $original;
        }
    }

    public function test_production_settlement_catalog_requires_exact_production_token(): void
    {
        $original = $this->app['env'];
        $this->app['env'] = 'production';

        try {
            $importer = Mockery::mock(IranSettlementCatalogImporter::class);
            $importer->shouldReceive('importPinnedSource')->once()->with(true, true)
                ->andReturn([
                    'validated' => 99317,
                    'existing' => 0,
                    'would_insert' => 99317,
                    'applied' => 99317,
                    'mode' => 'apply',
                ]);
            $this->app->instance(IranSettlementCatalogImporter::class, $importer);

            $this->assertSame(1, Artisan::call('location:iran-1404-settlement-catalog', [
                '--apply' => true,
                '--confirm' => 'APPLY-IR-SETTLEMENT-CATALOG-ISOLATED',
            ]));

            $this->assertSame(0, Artisan::call('location:iran-1404-settlement-catalog', [
                '--apply' => true,
                '--confirm' => 'APPLY-IR-SETTLEMENT-CATALOG-PRODUCTION',
            ]));
        } finally {
            $this->app['env'] = $original;
        }
    }
}
