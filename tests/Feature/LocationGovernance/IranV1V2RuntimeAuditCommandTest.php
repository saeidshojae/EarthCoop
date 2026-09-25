<?php

namespace Tests\Feature\LocationGovernance;

use App\Models\GovernanceArea;
use App\Models\Group;
use App\Models\LocationExternalId;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\Support\LocationGovernance\LocationFixture;
use Tests\TestCase;

final class IranV1V2RuntimeAuditCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_runtime_audit_is_read_only_and_reports_dependencies_and_blockers(): void
    {
        $schema = LocationFixture::iranSchema();
        $location = LocationFixture::createPath($schema, ['country'])->last();
        LocationExternalId::query()->create([
            'location_id' => $location->id,
            'source' => 'earthcoop-reference',
            'dataset_version' => 'v1',
            'external_id' => 'IR-COUNTRY',
            'metadata' => ['fixture' => true],
        ]);

        $user = User::factory()->create();
        DB::table('user_location_relationships')->insert([
            'user_id' => $user->id,
            'location_id' => $location->id,
            'relationship_type' => 'primary_residence',
            'started_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $area = GovernanceArea::factory()->official()->create(['country_code' => 'IR']);
        $area->locations()->attach($location->id);
        Group::query()->create([
            'group_type' => 'public',
            'name' => 'گروه تست وابستگی v1',
            'governance_area_id' => $area->id,
            'dimension_key' => 'public',
            'dimension_value_key' => 'audit-fixture',
            'is_open' => true,
        ]);

        $before = [
            'locations' => DB::table('locations')->count(),
            'external_ids' => DB::table('location_external_ids')->count(),
            'relationships' => DB::table('user_location_relationships')->count(),
            'groups' => DB::table('groups')->count(),
        ];

        $this->assertSame(0, Artisan::call('location:iran-v1-v2-runtime-audit', ['--json' => true]));
        $report = json_decode(trim(Artisan::output()), true, 512, JSON_THROW_ON_ERROR);

        $this->assertSame('read_only', $report['mode']);
        $this->assertSame(1, $report['v1_identity_count']);
        $this->assertSame(1, $report['reviewed_mapping_present']);
        $this->assertTrue($report['shared_cutover_blocked']);
        $this->assertGreaterThanOrEqual(3, $report['dependency_count_total']);
        $this->assertSame(1, $report['mapped_dependency_rows']);
        $country = collect($report['rows'])->firstWhere('v1_external_id', 'IR-COUNTRY');
        $this->assertSame('IR-1404-1', $country['candidate_v2_external_id']);
        $this->assertSame(1, $country['dependencies']['user_location_relationships']);
        $this->assertSame(1, $country['dependencies']['governance_area_locations']);
        $this->assertSame(1, $country['dependencies']['groups_via_governance']);

        $after = [
            'locations' => DB::table('locations')->count(),
            'external_ids' => DB::table('location_external_ids')->count(),
            'relationships' => DB::table('user_location_relationships')->count(),
            'groups' => DB::table('groups')->count(),
        ];
        $this->assertSame($before, $after);
    }

    public function test_source_backed_sari_region_mapping_is_verified_and_does_not_block_without_dependencies(): void
    {
        $schema = LocationFixture::iranSchema();
        $location = LocationFixture::createPath($schema, ['country', 'province', 'county', 'section', 'city', 'urban_region'])->last();
        LocationExternalId::query()->create([
            'location_id' => $location->id,
            'source' => 'earthcoop-reference',
            'dataset_version' => 'v1',
            'external_id' => 'IR-SARI-URBAN-01',
            'metadata' => ['fixture' => true],
        ]);

        $this->assertSame(0, Artisan::call('location:iran-v1-v2-runtime-audit', ['--json' => true]));
        $report = json_decode(trim(Artisan::output()), true, 512, JSON_THROW_ON_ERROR);
        $row = collect($report['rows'])->firstWhere('v1_external_id', 'IR-SARI-URBAN-01');

        $this->assertSame('verified_identity', $row['mapping_status']);
        $this->assertSame('IR-1404-5984', $row['candidate_v2_external_id']);
        $this->assertFalse($report['shared_cutover_blocked']);
    }

    public function test_unreviewed_v1_identity_blocks_shared_cutover_without_mutation(): void
    {
        $schema = LocationFixture::iranSchema();
        $location = LocationFixture::createPath($schema, ['country'])->last();
        LocationExternalId::query()->create([
            'location_id' => $location->id,
            'source' => 'earthcoop-reference',
            'dataset_version' => 'v1',
            'external_id' => 'IR-SYNTHETIC-UNKNOWN',
            'metadata' => ['fixture' => true],
        ]);

        $this->assertSame(0, Artisan::call('location:iran-v1-v2-runtime-audit', ['--json' => true]));
        $report = json_decode(trim(Artisan::output()), true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame(1, $report['unreviewed_v1_identity_count']);
        $this->assertTrue($report['shared_cutover_blocked']);
        $this->assertDatabaseHas('location_external_ids', ['external_id' => 'IR-SYNTHETIC-UNKNOWN']);
    }
}
