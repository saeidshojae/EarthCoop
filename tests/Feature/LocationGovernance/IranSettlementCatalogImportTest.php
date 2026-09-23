<?php

namespace Tests\Feature\LocationGovernance;

use App\Models\Location;
use App\Services\LocationGovernance\Import\IranSettlementCatalogImporter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Tests\TestCase;

final class IranSettlementCatalogImportTest extends TestCase
{
    use RefreshDatabase;

    private array $paths = [];

    protected function tearDown(): void
    {
        foreach ($this->paths as $path) {
            @unlink($path);
        }
        parent::tearDown();
    }

    private function fixture(bool $privileged = false): array
    {
        $review = tempnam(sys_get_temp_dir(), 'settlement-review-');
        $manifest = tempnam(sys_get_temp_dir(), 'settlement-manifest-');
        $this->paths[] = $review;
        $this->paths[] = $manifest;
        $rows = [];
        foreach ([201, 202] as $id) {
            $rows[] = [
                'external_id' => 'IR-1404-'.$id,
                'parent_external_id' => 'IR-1404-100',
                'type' => 'settlement',
                'canonical_name' => 'آبادی نمونه '.$id,
                'classification' => 'unverified_settlement',
                'provenance' => [
                    'source_commit' => IranSettlementCatalogImporter::SOURCE_COMMIT,
                    'source_division_type' => 6,
                    'source_code' => (string) $id,
                    'source_row_id' => $id,
                ],
                'metadata' => [
                    'residential_eligibility' => 'unverified',
                    'is_residence_endpoint' => false,
                    'importable' => false,
                    'governance_authorized' => $privileged && $id === 201,
                ],
            ];
        }
        file_put_contents($review, implode("\n", array_map(
            fn (array $row): string => json_encode($row, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR), $rows
        ))."\n");
        file_put_contents($manifest, json_encode([
            'settlements_review_sha256' => hash_file('sha256', $review),
            'source_commit' => IranSettlementCatalogImporter::SOURCE_COMMIT,
            'dataset_version' => 'v2',
            'quarantined_settlements' => 2,
            'settlement_classification' => [
                'residential_villages_verified' => 0,
                'governance_authorized' => 0,
            ],
        ], JSON_THROW_ON_ERROR));
        return [$review, $manifest];
    }

    public function test_dry_run_validates_without_any_database_writes(): void
    {
        [$review, $manifest] = $this->fixture();
        $before = Location::count();
        $result = app(IranSettlementCatalogImporter::class)->import($review, $manifest, false, 2);
        $this->assertSame(['validated' => 2, 'applied' => 0, 'mode' => 'dry-run'], $result);
        $this->assertSame(0, DB::table('reference_settlements')->count());
        $this->assertSame($before, Location::count());
    }

    public function test_privileged_settlement_is_rejected_before_any_write(): void
    {
        [$review, $manifest] = $this->fixture(true);
        try {
            app(IranSettlementCatalogImporter::class)->import($review, $manifest, true, 2);
            $this->fail('Privileged settlement was accepted.');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('Invalid, duplicate or privileged', $e->getMessage());
        }
        $this->assertSame(0, DB::table('reference_settlements')->count());
    }

    public function test_tampered_review_file_fails_closed(): void
    {
        [$review, $manifest] = $this->fixture();
        file_put_contents($review, "{}", FILE_APPEND);
        $this->expectException(InvalidArgumentException::class);
        app(IranSettlementCatalogImporter::class)->import($review, $manifest, true, 2);
    }

    public function test_repeat_import_is_idempotent_and_changed_evidence_is_not_overwritten(): void
    {
        [$review, $manifest] = $this->fixture();
        $importer = app(IranSettlementCatalogImporter::class);
        $this->assertSame(2, $importer->import($review, $manifest, true, 2)['applied']);
        $this->assertSame(0, $importer->import($review, $manifest, true, 2)['applied']);
        $this->assertSame(2, DB::table('reference_settlements')->count());

        DB::table('reference_settlements')->where('external_id', 'IR-1404-201')
            ->update(['residential_eligibility' => 'verified']);
        try {
            $importer->import($review, $manifest, true, 2);
            $this->fail('Changed evidence was silently overwritten.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('classification changed', $e->getMessage());
        }
        $this->assertSame('verified', DB::table('reference_settlements')
            ->where('external_id', 'IR-1404-201')->value('residential_eligibility'));
    }

    public function test_valid_settlements_do_not_create_operational_locations(): void
    {
        [$review, $manifest] = $this->fixture();
        $locations = Location::count();
        $result = app(IranSettlementCatalogImporter::class)->import($review, $manifest, true, 2);
        $this->assertSame(2, $result['applied']);
        $this->assertSame(2, DB::table('reference_settlements')->count());
        $this->assertSame(0, DB::table('reference_settlements')->where('governance_authorized', true)->count());
        $this->assertSame(0, DB::table('reference_settlements')->where('operational_promotion_allowed', true)->count());
        $this->assertSame($locations, Location::count());
    }
}
