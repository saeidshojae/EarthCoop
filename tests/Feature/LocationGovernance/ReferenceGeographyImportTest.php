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
        $this->assertStringContainsString('IR-MAZ-SARI-CHAHARDANGEH-KIASAR', $dataset);
        $this->assertStringContainsString('Kiasar', $dataset);
        $this->assertStringContainsString('IR-SARI-URBAN-NONEIGHBORHOOD', $dataset);
        $this->assertStringContainsString('Sari Reference Region Without Neighborhood', $dataset);
        $this->assertStringContainsString('rural_district', $dataset);
        $this->assertStringContainsString('village', $dataset);
        $this->assertStringContainsString('without-neighborhood', $dataset);
    }

    public function test_reference_import_exposes_kiasar_under_chahardangeh_with_city_structural_choices(): void
    {
        $exit = Artisan::call('location:reference-import', [
            'country' => 'IR',
            '--dataset-version' => 'v1',
            '--apply' => true,
        ]);
        $this->assertSame(0, $exit, Artisan::output());

        $kiasar = Location::query()->where('canonical_name', 'Kiasar')->sole();
        $this->assertSame('city', $kiasar->type?->key);
        $this->assertSame('Chahardangeh Section', $kiasar->parent?->canonical_name);

        $citySchemaType = LocationSchemaType::query()
            ->where('location_schema_id', $kiasar->location_schema_id)
            ->where('location_type_id', $kiasar->location_type_id)
            ->sole();

        $this->assertSame(
            ['single_urban_region', 'no_urban_region'],
            data_get($citySchemaType->metadata, 'structural_claim_types'),
        );
        $this->assertEqualsCanonicalizing(
            ['single_neighborhood', 'no_neighborhood'],
            data_get($citySchemaType->metadata, 'structural_claim_types_after.no_urban_region'),
        );
    }

    public function test_reference_import_persists_the_established_user_location_proposal_policy(): void
    {
        $exit = Artisan::call('location:reference-import', [
            'country' => 'IR',
            '--dataset-version' => 'v1',
            '--apply' => true,
        ]);
        $this->assertSame(0, $exit, Artisan::output());

        $rows = LocationSchemaType::query()->with('type')->get()->keyBy(fn (LocationSchemaType $row) => $row->type?->key);

        // The pre-canonical registration flow allowed users to add the
        // "region / village" tier and every finer tier. In the canonical
        // split this means urban_region and village are user-proposable,
        // together with neighborhood and all micro-location descendants.
        foreach (['village', 'urban_region', 'neighborhood', 'street', 'alley', 'complex', 'building'] as $key) {
            $this->assertTrue((bool) data_get($rows[$key]?->metadata, 'crowdsourced_proposal_allowed'), "{$key} must remain crowdsourcable.");
        }
        foreach (['country', 'province', 'county', 'section', 'city', 'rural_district'] as $key) {
            $this->assertFalse((bool) data_get($rows[$key]?->metadata, 'crowdsourced_proposal_allowed'), "{$key} must not become crowdsourcable implicitly.");
        }

        foreach (['urban_region', 'village'] as $key) {
            $this->assertEqualsCanonicalizing(
                ['single_neighborhood', 'no_neighborhood'],
                data_get($rows[$key]?->metadata, 'structural_claim_types', []),
                "{$key} must expose the canonical no/single-neighborhood structural contract."
            );
        }

        $city = Location::query()->where('level', 'city')->firstOrFail();
        $ruralDistrict = Location::query()->where('level', 'rural_district')->firstOrFail();
        $urbanRegion = Location::query()->where('level', 'urban_region')->firstOrFail();
        $village = Location::query()->where('level', 'village')->firstOrFail();
        $neighborhood = Location::query()->where('level', 'neighborhood')->firstOrFail();
        $policy = app(LocationProposalPolicy::class);

        $this->assertTrue($policy->allows($city, $rows['urban_region']->type));
        $this->assertTrue($policy->allows($ruralDistrict, $rows['village']->type));
        $this->assertTrue($policy->allows($urbanRegion, $rows['neighborhood']->type));
        $this->assertTrue($policy->allows($village, $rows['neighborhood']->type));
        $this->assertTrue($policy->allows($neighborhood, $rows['street']->type));
    }
}
