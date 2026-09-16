<?php

namespace Tests\Feature\LocationGovernance;

use App\Models\Location;
use App\Models\LocationSchemaType;
use App\Services\LocationGovernance\LocationProposalPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class ReferenceGeographyImportTest extends TestCase
{
    use RefreshDatabase;

    public function test_dry_run_reports_diff_without_writing_and_reference_dataset_covers_required_iran_paths(): void
    {
        $before = Location::count();

        $exit = Artisan::call('location:reference-import', [
            'country' => 'IR',
            '--dataset-version' => 'v1',
            '--dry-run' => true,
        ]);
        $output = Artisan::output();

        $this->assertSame(0, $exit, $output);
        $this->assertSame($before, Location::count(), 'Dry-run must not persist reference geography.');
        $this->assertStringContainsString('create', strtolower($output));
        $this->assertStringContainsString('update', strtolower($output));
        $this->assertStringContainsString('deactivate', strtolower($output));
        $this->assertStringContainsString('conflict', strtolower($output));

        $dataset = file_get_contents(database_path('reference/ir/v1/locations.jsonl'));
        $this->assertNotFalse($dataset);
        $this->assertStringContainsString('Sari', $dataset);
        $this->assertStringContainsString('Chahardangeh', $dataset);
        $this->assertStringContainsString('rural_district', $dataset);
        $this->assertStringContainsString('village', $dataset);
        $this->assertStringContainsString('without-neighborhood', $dataset);
    }

    public function test_reference_import_persists_the_reviewed_micro_location_proposal_policy(): void
    {
        $exit = Artisan::call('location:reference-import', [
            'country' => 'IR',
            '--dataset-version' => 'v1',
            '--apply' => true,
        ]);
        $this->assertSame(0, $exit, Artisan::output());

        $rows = LocationSchemaType::query()->with('type')->get()->keyBy(fn (LocationSchemaType $row) => $row->type?->key);

        foreach (['street', 'alley', 'complex', 'building'] as $key) {
            $this->assertTrue((bool) data_get($rows[$key]?->metadata, 'crowdsourced_proposal_allowed'), "{$key} must remain crowdsourcable.");
        }
        foreach (['country', 'province', 'county', 'section', 'city', 'rural_district', 'village', 'urban_region', 'neighborhood'] as $key) {
            $this->assertFalse((bool) data_get($rows[$key]?->metadata, 'crowdsourced_proposal_allowed'), "{$key} must not become crowdsourcable implicitly.");
        }

        $neighborhood = Location::query()->where('level', 'neighborhood')->firstOrFail();
        $this->assertTrue(app(LocationProposalPolicy::class)->allows($neighborhood, $rows['street']->type));
    }
}
