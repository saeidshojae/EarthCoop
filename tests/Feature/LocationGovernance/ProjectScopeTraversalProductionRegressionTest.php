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

        $global = GovernanceArea::factory()->official()->create([
            'parent_id' => null,
            'governance_type' => 'global',
            'canonical_name' => 'EarthCoop Global',
            'localized_names' => ['fa' => 'جهانی'],
            'status' => 'active',
        ]);
        $asia = GovernanceArea::factory()->official()->create([
            'parent_id' => $global->id,
            'governance_type' => 'continent',
            'canonical_name' => 'Asia',
            'localized_names' => ['fa' => 'آسیا'],
            'status' => 'active',
        ]);
        $iran = GovernanceArea::factory()->official()->create([
            'parent_id' => $asia->id,
            'governance_type' => 'country',
            'canonical_name' => 'Iran',
            'localized_names' => ['fa' => 'ایران'],
            'status' => 'active',
        ]);
        $iran->locations()->attach($country->id);

        $root = $this->getJson('/location/project-scope/options/root');
        $root->assertOk()
            ->assertJsonPath('data.0.identity', 'governance:'.$global->id)
            ->assertJsonPath('data.0.type_key', 'global')
            ->assertJsonPath('data.0.label', 'جهانی');

        $continents = $this->getJson('/location/project-scope/options/governance/'.$global->id.'/children');
        $continents->assertOk()
            ->assertJsonPath('data.0.identity', 'governance:'.$asia->id)
            ->assertJsonPath('data.0.type_key', 'continent')
            ->assertJsonPath('data.0.label', 'آسیا');

        $countries = $this->getJson('/location/project-scope/options/governance/'.$asia->id.'/children');
        $countries->assertOk()
            ->assertJsonPath('data.0.identity', 'location:'.$country->id)
            ->assertJsonPath('data.0.type_key', 'country')
            ->assertJsonPath('data.0.label', 'ایران')
            ->assertJsonPath('data.0.governance_area_id', $iran->id);
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
            ->map(fn (string $line): array => json_decode($line, true, flags: JSON_THROW_ON_ERROR))
            ->keyBy('external_id');

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
}
