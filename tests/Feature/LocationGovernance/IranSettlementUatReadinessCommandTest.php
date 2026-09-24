<?php

namespace Tests\Feature\LocationGovernance;

use App\Models\LocationExternalId;
use App\Models\ReferenceSettlement;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\Support\LocationGovernance\LocationFixture;
use Tests\TestCase;

final class IranSettlementUatReadinessCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_readiness_fails_closed_when_catalog_or_reviewed_v1_identities_are_missing(): void
    {
        $exit = Artisan::call('location:iran-settlement-uat-readiness', ['--json' => true]);

        $this->assertSame(1, $exit);
        $report = json_decode(trim(Artisan::output()), true, 512, JSON_THROW_ON_ERROR);

        $this->assertSame('read_only', $report['mode']);
        $this->assertFalse($report['ready_for_manual_uat']);
        $this->assertSame(0, $report['settlement_count']);
        $this->assertNotEmpty($report['blockers']);
    }

    public function test_readiness_can_pass_in_testing_with_complete_catalog_and_reviewed_v1_identities(): void
    {
        $schema = LocationFixture::iranSchema();

        foreach ((array) config('iran_v1_v2_crosswalk.mappings', []) as $v1 => $mapping) {
            if (($mapping['status'] ?? null) !== 'verified_identity') {
                continue;
            }

            $location = LocationFixture::createPath($schema, ['country'])->last();
            LocationExternalId::query()->create([
                'location_id' => $location->id,
                'source' => 'earthcoop-reference',
                'dataset_version' => 'v1',
                'external_id' => $v1,
                'metadata' => ['fixture' => true],
            ]);
        }

        // The production importer proves the fixed full count separately. This
        // command's behavior test uses a configurable expected count override
        // through a partial mock of the database count would add noise, so use
        // direct rows plus a temporary config-backed expectation.
        config()->set('iran_settlement_catalog.uat_expected_count', 2);

        foreach ([91001, 91002] as $id) {
            ReferenceSettlement::query()->create([
                'source' => 'IranCountryDivisions/geo_1404',
                'dataset_version' => 'v2',
                'external_id' => 'IR-1404-'.$id,
                'parent_external_id' => 'IR-1404-1938',
                'source_code' => (string) $id,
                'source_row_id' => $id,
                'name_fa' => 'آبادی آمادگی '.$id,
                'search_name' => 'آبادی آمادگی '.$id,
                'classification' => 'unverified_settlement',
                'residential_eligibility' => 'unverified',
                'governance_authorized' => false,
                'operational_promotion_allowed' => false,
                'provenance' => ['source' => 'fixture'],
            ]);
        }

        $exit = Artisan::call('location:iran-settlement-uat-readiness', ['--json' => true]);
        $report = json_decode(trim(Artisan::output()), true, 512, JSON_THROW_ON_ERROR);

        $this->assertSame(0, $exit);
        $this->assertTrue($report['ready_for_manual_uat']);
        $this->assertSame(2, $report['settlement_count']);
        $this->assertSame([], $report['missing_verified_v1_identities']);
    }
}
