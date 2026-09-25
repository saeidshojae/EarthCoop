<?php

namespace Tests\Feature\LocationGovernance;

use App\Models\LocationSchemaType;
use App\Services\LocationGovernance\LocationStructureClaimPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\LocationGovernance\LocationFixture;
use Tests\TestCase;

final class RepairReferenceStructuralMetadataTest extends TestCase
{
    use RefreshDatabase;

    public function test_dry_run_does_not_write_and_explicit_apply_restores_village_choices_without_touching_custom_metadata(): void
    {
        $schema = LocationFixture::iranSchema();
        $villageType = $schema->types->firstWhere('key', 'village');
        $pivot = LocationSchemaType::query()
            ->where('location_schema_id', $schema->id)
            ->where('location_type_id', $villageType->id)
            ->firstOrFail();
        $pivot->forceFill(['metadata' => [
            'crowdsourced_proposal_allowed' => true,
            'custom_legacy_setting' => 'preserved',
        ]])->save();

        $village = LocationFixture::createPath(
            $schema,
            ['country', 'province', 'county', 'section', 'rural_district', 'village']
        )->last();
        $policy = app(LocationStructureClaimPolicy::class);
        $this->assertSame([], $policy->allowedClaimTypes($village));

        $this->artisan('location-governance:repair-reference-structural-metadata')
            ->assertExitCode(0);
        $this->assertSame([], $pivot->fresh()->metadata['structural_claim_types'] ?? []);

        $this->artisan('location-governance:repair-reference-structural-metadata', [
            '--apply' => true,
            '--confirm' => 'incorrect',
        ])->assertExitCode(1);
        $this->assertSame([], $pivot->fresh()->metadata['structural_claim_types'] ?? []);

        $locationCount = \App\Models\Location::query()->count();
        $this->artisan('location-governance:repair-reference-structural-metadata', [
            '--apply' => true,
            '--confirm' => 'APPLY-REFERENCE-STRUCTURAL-METADATA-IR-v1',
        ])->assertExitCode(0);

        $this->assertSame(
            ['single_neighborhood', 'no_neighborhood'],
            $policy->allowedClaimTypes($village)
        );
        $this->assertSame('preserved', $pivot->fresh()->metadata['custom_legacy_setting']);
        $this->assertSame($locationCount, \App\Models\Location::query()->count());

        // A repeated apply must be idempotent and must not remove metadata.
        $this->artisan('location-governance:repair-reference-structural-metadata', [
            '--apply' => true,
            '--confirm' => 'APPLY-REFERENCE-STRUCTURAL-METADATA-IR-v1',
        ])->assertExitCode(0);
        $this->assertSame('preserved', $pivot->fresh()->metadata['custom_legacy_setting']);
    }
}
