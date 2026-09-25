<?php

namespace Tests\Feature\LocationGovernance;

use App\Models\GovernanceArea;
use App\Services\Projects\ProjectScopeCutoverRenderer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\LocationGovernance\LocationFixture;
use Tests\TestCase;

class ProjectScopeTraversalProductionRegressionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'location-governance.runtime_enabled' => true,
            'location-governance.projects_enabled' => true,
        ]);
    }

    public function test_project_scope_bridges_global_and_continent_governance_into_country_location_tree(): void
    {
        $schema = LocationFixture::iranSchema();
        $country = LocationFixture::createPath($schema, ['country'])->first();
        [$global, $asia, $iran] = $this->governancePathFor($country);

        $this->getJson('/location/project-scope/options/root')->assertOk()
            ->assertJsonPath('data.0.identity', 'governance:'.$global->id)
            ->assertJsonPath('data.0.type_key', 'global')->assertJsonPath('data.0.label', 'جهانی');
        $this->getJson('/location/project-scope/options/governance/'.$global->id.'/children')->assertOk()
            ->assertJsonPath('data.0.identity', 'governance:'.$asia->id)
            ->assertJsonPath('data.0.type_key', 'continent')->assertJsonPath('data.0.label', 'آسیا');
        $this->getJson('/location/project-scope/options/governance/'.$asia->id.'/children')->assertOk()
            ->assertJsonPath('data.0.identity', 'location:'.$country->id)
            ->assertJsonPath('data.0.type_key', 'country')->assertJsonPath('data.0.label', 'ایران')
            ->assertJsonPath('data.0.governance_area_id', $iran->id);
    }

    public function test_project_scope_labels_fall_back_to_persian_for_regional_locale(): void
    {
        app()->setLocale('fa-IR');
        $schema = LocationFixture::iranSchema();
        $country = LocationFixture::createPath($schema, ['country'])->last();
        [$global, $asia, $iran] = $this->governancePathFor($country);

        $this->getJson('/location/project-scope/options/root')->assertOk()
            ->assertJsonPath('data.0.label', 'جهانی');
        $this->getJson('/location/project-scope/options/governance/'.$global->id.'/children')->assertOk()
            ->assertJsonPath('data.0.label', 'آسیا');
        $this->getJson('/location/project-scope/options/governance/'.$asia->id.'/children')->assertOk()
            ->assertJsonPath('data.0.label', 'ایران')
            ->assertJsonPath('data.0.governance_area_id', $iran->id);
    }

    public function test_project_scope_prefers_v2_iran_country_when_legacy_v1_coexists_under_asia(): void
    {
        config(['iran_settlement_catalog.v2_runtime_enabled' => true]);
        $schema = LocationFixture::iranSchema();
        $countryType = $schema->types->firstWhere('key', 'country');
        $v1Location = \App\Models\Location::factory()->create([
            'location_schema_id' => $schema->id,
            'location_type_id' => $countryType->id,
            'country_code' => 'IR',
            'name' => 'Iran legacy',
            'canonical_name' => 'Iran legacy',
            'localized_names' => ['fa' => 'ایران قدیمی'],
            'level' => 'country',
            'status' => 'active',
        ]);
        $v2Location = \App\Models\Location::factory()->create([
            'location_schema_id' => $schema->id,
            'location_type_id' => $countryType->id,
            'country_code' => 'IR',
            'name' => 'ایران',
            'canonical_name' => 'ایران',
            'localized_names' => ['fa' => 'ایران'],
            'level' => 'country',
            'status' => 'active',
        ]);

        $global = GovernanceArea::factory()->official()->create([
            'key' => 'earthcoop-global',
            'parent_id' => null,
            'governance_type' => 'global',
            'canonical_name' => 'EarthCoop Global',
            'localized_names' => ['fa' => 'جهانی'],
            'status' => 'active',
        ]);
        $asia = GovernanceArea::factory()->official()->create([
            'key' => 'earthcoop-continent-asia',
            'parent_id' => $global->id,
            'governance_type' => 'continent',
            'canonical_name' => 'Asia',
            'localized_names' => ['fa' => 'آسیا'],
            'status' => 'active',
        ]);
        $v1 = GovernanceArea::factory()->official()->create([
            'key' => 'ir-reference-v1-country',
            'parent_id' => $asia->id,
            'country_code' => 'IR',
            'governance_type' => 'country',
            'canonical_name' => 'Iran legacy',
            'localized_names' => ['fa' => 'ایران قدیمی'],
            'metadata' => ['reference_topology' => true, 'source' => 'earthcoop-reference-governance', 'dataset_version' => 'v1'],
            'status' => 'active',
        ]);
        $v2 = GovernanceArea::factory()->official()->create([
            'key' => 'ir-reference-v2-ir-1404-1',
            'parent_id' => $asia->id,
            'country_code' => 'IR',
            'governance_type' => 'country',
            'canonical_name' => 'ایران',
            'localized_names' => ['fa' => 'ایران'],
            'metadata' => ['reference_topology' => true, 'source' => 'earthcoop-reference-governance', 'dataset_version' => 'v2'],
            'status' => 'active',
        ]);
        $v1->locations()->attach($v1Location->id);
        $v2->locations()->attach($v2Location->id);

        $response = $this->getJson('/location/project-scope/options/governance/'.$asia->id.'/children')->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.governance_area_id', $v2->id);
        $response->assertJsonPath('data.0.identity', 'location:'.$v2Location->id);
    }

    public function test_saved_governance_scope_path_can_stop_at_continent(): void
    {
        $schema = LocationFixture::iranSchema();
        $country = LocationFixture::createPath($schema, ['country'])->first();
        [$global, $asia] = $this->governancePathFor($country);

        $this->getJson('/location/project-scope/options/path?governance_area_id='.$asia->id)->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.identity', 'governance:'.$global->id)
            ->assertJsonPath('data.1.identity', 'governance:'.$asia->id);
    }

    public function test_saved_location_scope_path_hydrates_governance_bridge_and_every_location_ancestor(): void
    {
        $schema = LocationFixture::iranSchema();
        $path = LocationFixture::createPath(
            $schema,
            ['country', 'province', 'county', 'section', 'city', 'urban_region', 'neighborhood'],
            ['Iran', 'Mazandaran', 'Sari County', 'Central Sari', 'Sari', 'Urban Region 1', 'Reference Neighborhood'],
        );
        [$global, $asia] = $this->governancePathFor($path->first());
        $target = $path->last();

        $this->getJson('/location/project-scope/options/path?target_location_id='.$target->id)->assertOk()
            ->assertJsonCount(2 + $path->count(), 'data')
            ->assertJsonPath('data.0.identity', 'governance:'.$global->id)
            ->assertJsonPath('data.1.identity', 'governance:'.$asia->id)
            ->assertJsonPath('data.2.identity', 'location:'.$path->first()->id)
            ->assertJsonPath('data.'.(1 + $path->count()).'.identity', 'location:'.$target->id);
    }

    public function test_canonical_project_scope_renderer_emits_the_governance_field_required_by_the_picker(): void
    {
        $legacyHtml = <<<'HTML'
<form>
<label>قاره:</label>
<select id="geographic_continent_select"><option value="">انتخاب کنید</option></select>
<script>initializeGeographicLocation();</script>
</form>
HTML;
        $html = app(ProjectScopeCutoverRenderer::class)->renderCanonical($legacyHtml, null, 42);
        $this->assertStringContainsString('name="governance_area_id"', $html);
        $this->assertStringContainsString('value="42" data-project-governance-area-id', $html);
        $this->assertStringContainsString('name="target_location_id"', $html);
    }

    public function test_reference_rural_branch_contains_a_full_arbitrary_depth_path_below_reference_village(): void
    {
        $rows = collect(file(database_path('reference/ir/v1/locations.jsonl'), FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES))
            ->map(fn (string $line): array => json_decode($line, true, flags: JSON_THROW_ON_ERROR))->keyBy('external_id');
        $expected = [
            'IR-MAZ-SARI-CHAHARDANGEH-VILLAGE-NBH-01' => ['IR-MAZ-SARI-CHAHARDANGEH-VILLAGE-01', 'neighborhood'],
            'IR-MAZ-SARI-CHAHARDANGEH-VILLAGE-STREET-01' => ['IR-MAZ-SARI-CHAHARDANGEH-VILLAGE-NBH-01', 'street'],
            'IR-MAZ-SARI-CHAHARDANGEH-VILLAGE-ALLEY-01' => ['IR-MAZ-SARI-CHAHARDANGEH-VILLAGE-STREET-01', 'alley'],
            'IR-MAZ-SARI-CHAHARDANGEH-VILLAGE-COMPLEX-01' => ['IR-MAZ-SARI-CHAHARDANGEH-VILLAGE-ALLEY-01', 'complex'],
            'IR-MAZ-SARI-CHAHARDANGEH-VILLAGE-BUILDING-01' => ['IR-MAZ-SARI-CHAHARDANGEH-VILLAGE-COMPLEX-01', 'building'],
        ];
        foreach ($expected as $externalId => [$parentExternalId, $type]) {
            $this->assertTrue($rows->has($externalId), 'Missing reference location '.$externalId);
            $this->assertSame($parentExternalId, $rows[$externalId]['parent_external_id']);
            $this->assertSame($type, $rows[$externalId]['type']);
            $this->assertSame('active', $rows[$externalId]['status']);
        }
    }

    private function governancePathFor($country): array
    {
        $global = GovernanceArea::factory()->official()->create(['parent_id' => null, 'governance_type' => 'global', 'canonical_name' => 'EarthCoop Global', 'localized_names' => ['fa' => 'جهانی'], 'status' => 'active']);
        $asia = GovernanceArea::factory()->official()->create(['parent_id' => $global->id, 'governance_type' => 'continent', 'canonical_name' => 'Asia', 'localized_names' => ['fa' => 'آسیا'], 'status' => 'active']);
        $iran = GovernanceArea::factory()->official()->create(['parent_id' => $asia->id, 'governance_type' => 'country', 'canonical_name' => 'Iran', 'localized_names' => ['fa' => 'ایران'], 'status' => 'active']);
        $iran->locations()->attach($country->id);
        return [$global, $asia, $iran];
    }
}
