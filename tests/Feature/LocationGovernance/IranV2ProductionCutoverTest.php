<?php

namespace Tests\Feature\LocationGovernance;

use App\Models\Group;
use App\Models\GroupUser;
use App\Models\LocationExternalId;
use App\Models\LocationProposal;
use App\Models\User;
use App\Models\UserLocationRelationship;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class IranV2ProductionCutoverTest extends TestCase
{
    use RefreshDatabase;

    public function test_final_cutover_blocks_unmapped_live_dependencies_then_preserves_verified_runtime_identity_and_history(): void
    {
        $this->assertSame(0, Artisan::call('location:reference-import', [
            'country' => 'IR',
            '--dataset-version' => 'v1',
            '--apply' => true,
        ]), Artisan::output());

        $this->assertSame(0, Artisan::call('location-governance:reference-topology', [
            'country' => 'IR',
            '--dataset-version' => 'v1',
            '--apply' => true,
        ]), Artisan::output());

        $this->assertSame(0, Artisan::call('location:reference-import', [
            'country' => 'IR',
            '--dataset-version' => 'v2',
            '--apply' => true,
            '--confirm' => 'APPLY-IR-1404-V2-UAT',
        ]), Artisan::output());

        $source = 'earthcoop-reference';
        $v1Region = LocationExternalId::query()
            ->where('source', $source)
            ->where('dataset_version', 'v1')
            ->where('external_id', 'IR-SARI-URBAN-01')
            ->firstOrFail()
            ->location()
            ->firstOrFail();
        $v2Region = LocationExternalId::query()
            ->where('source', $source)
            ->where('dataset_version', 'v2')
            ->where('external_id', 'IR-1404-5984')
            ->firstOrFail()
            ->location()
            ->firstOrFail();
        $unmappedNeighborhood = LocationExternalId::query()
            ->where('source', $source)
            ->where('dataset_version', 'v1')
            ->where('external_id', 'IR-SARI-NBH-01')
            ->firstOrFail()
            ->location()
            ->firstOrFail();

        $blockerUser = User::factory()->create();
        $blocker = UserLocationRelationship::query()->create([
            'user_id' => $blockerUser->id,
            'location_id' => $unmappedNeighborhood->id,
            'relationship_type' => 'primary_residence',
            'started_at' => now()->subHour(),
        ]);

        $this->assertSame(1, Artisan::call('location:iran-v2-production-cutover', [
            '--dry-run' => true,
        ]));
        $blockedOutput = Artisan::output();
        $this->assertStringContainsString('READY_FOR_FINAL_CUTOVER: NO', $blockedOutput);
        $this->assertStringContainsString('blocker unmapped_active_relationships: 1', $blockedOutput);

        $blocker->forceFill(['ended_at' => now()])->save();

        $user = User::factory()->create();
        $relationship = UserLocationRelationship::query()->create([
            'user_id' => $user->id,
            'location_id' => $v1Region->id,
            'relationship_type' => 'primary_residence',
            'started_at' => now()->subMinutes(30),
        ]);

        $v1Area = $v1Region->governanceAreas()
            ->official()
            ->active()
            ->orderByDesc('rank')
            ->firstOrFail();

        $group = Group::query()->create([
            'name' => 'Existing canonical public group',
            'group_type' => 0,
            'governance_area_id' => $v1Area->id,
            'dimension_key' => 'public',
            'dimension_value_key' => 'public',
            'location_level' => 'region',
        ]);
        GroupUser::query()->create([
            'group_id' => $group->id,
            'user_id' => $user->id,
            'role' => 1,
            'status' => 1,
            'expired' => null,
        ]);

        $neighborhoodTypeId = DB::table('location_types')->where('key', 'neighborhood')->value('id');
        $streetTypeId = DB::table('location_types')->where('key', 'street')->value('id');

        $rootProposal = LocationProposal::query()->create([
            'proposer_user_id' => $user->id,
            'parent_location_id' => $v1Region->id,
            'parent_location_proposal_id' => null,
            'location_schema_id' => $v1Region->location_schema_id,
            'location_type_id' => $neighborhoodTypeId,
            'country_code' => 'IR',
            'canonical_name' => 'Pending neighborhood preserved through cutover',
            'normalized_name' => 'pending neighborhood preserved through cutover',
            'status' => 'pending',
        ]);
        $childProposal = LocationProposal::query()->create([
            'proposer_user_id' => $user->id,
            'parent_location_id' => null,
            'parent_location_proposal_id' => $rootProposal->id,
            'location_schema_id' => $v1Region->location_schema_id,
            'location_type_id' => $streetTypeId,
            'country_code' => 'IR',
            'canonical_name' => 'Pending street preserved through cutover',
            'normalized_name' => 'pending street preserved through cutover',
            'status' => 'pending',
        ]);

        $readyExit = Artisan::call('location:iran-v2-production-cutover', [
            '--dry-run' => true,
        ]);
        $readyOutput = Artisan::output();
        $this->assertSame(0, $readyExit, $readyOutput);
        $this->assertStringContainsString('READY_FOR_FINAL_CUTOVER: YES', $readyOutput);
        $this->assertStringContainsString('blocker_total: 0', $readyOutput);

        $relationshipId = $relationship->id;
        $groupId = $group->id;
        $membershipId = GroupUser::query()
            ->where('group_id', $groupId)
            ->where('user_id', $user->id)
            ->value('id');

        $applyExit = Artisan::call('location:iran-v2-production-cutover', [
            '--apply' => true,
            '--confirm' => 'CUTOVER-IR-1404-V2-PRODUCTION',
        ]);
        $applyOutput = Artisan::output();
        $this->assertSame(0, $applyExit, $applyOutput);

        $v2Area = $v2Region->governanceAreas()
            ->official()
            ->active()
            ->orderByDesc('rank')
            ->firstOrFail();
        $v2SchemaId = DB::table('location_schemas')->where('key', 'ir-reference-v2')->value('id');

        $this->assertDatabaseHas('user_location_relationships', [
            'id' => $relationshipId,
            'user_id' => $user->id,
            'location_id' => $v2Region->id,
            'ended_at' => null,
        ]);
        $this->assertDatabaseHas('groups', [
            'id' => $groupId,
            'governance_area_id' => $v2Area->id,
            'dimension_key' => 'public',
            'dimension_value_key' => 'public',
        ]);
        $this->assertDatabaseHas('group_user', [
            'id' => $membershipId,
            'group_id' => $groupId,
            'user_id' => $user->id,
            'role' => 1,
            'status' => 1,
        ]);
        $this->assertDatabaseHas('location_proposals', [
            'id' => $rootProposal->id,
            'parent_location_id' => $v2Region->id,
            'location_schema_id' => $v2SchemaId,
        ]);
        $this->assertDatabaseHas('location_proposals', [
            'id' => $childProposal->id,
            'parent_location_proposal_id' => $rootProposal->id,
            'location_schema_id' => $v2SchemaId,
        ]);

        $schemaMetadata = json_decode((string) DB::table('location_schemas')
            ->where('key', 'ir-reference-v2')->value('metadata'), true);
        $this->assertTrue((bool) ($schemaMetadata['runtime_active'] ?? false));

        $topologyExit = Artisan::call('location-governance:reference-topology', [
            'country' => 'IR',
            '--dataset-version' => 'v2',
            '--dry-run' => true,
        ]);
        $topologyOutput = Artisan::output();
        $this->assertSame(0, $topologyExit, $topologyOutput);
        $this->assertStringContainsString('create: 0', $topologyOutput);
        $this->assertStringContainsString('update: 0', $topologyOutput);
        $this->assertStringContainsString('conflict: 0', $topologyOutput);
        $this->assertStringContainsString('unchanged: 6160', $topologyOutput);

        $completeExit = Artisan::call('location:iran-v2-production-cutover', [
            '--dry-run' => true,
        ]);
        $completeOutput = Artisan::output();
        $this->assertSame(0, $completeExit, $completeOutput);
        $this->assertStringContainsString('CUTOVER_COMPLETE: YES', $completeOutput);
        $this->assertStringContainsString('blocker_total: 0', $completeOutput);
    }
}
