<?php

namespace Tests\Feature\LocationGovernance;

use App\Enums\LocationGovernance\LocationProposalStatus;
use App\Models\LocationProposal;
use App\Models\LocationScopedGroupRequest;
use App\Models\User;
use App\Services\Groups\PendingLocationGroupRequestService;
use App\Services\LocationGovernance\LocationStructureClaimService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\LocationGovernance\LocationFixture;
use Tests\TestCase;

final class PendingGroupLocalizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_existing_structural_request_uses_live_persian_village_name_without_rewriting_legacy_canonical_metadata(): void
    {
        $schema = LocationFixture::iranSchema();
        $village = LocationFixture::createPath($schema, [
            'country', 'province', 'county', 'section', 'rural_district', 'village',
        ])->last();
        $village->forceFill([
            'canonical_name' => 'Reference Village Without Neighborhood',
            'localized_names' => ['fa' => 'روستای مرجع بدون محله'],
        ])->save();
        $user = User::factory()->create();
        $claim = app(LocationStructureClaimService::class)
            ->findOrCreateOpenClaim($village, 'no_neighborhood', $user);
        $request = LocationScopedGroupRequest::create([
            'requester_user_id' => $user->id,
            'location_structure_claim_id' => $claim->id,
            'scope_kind' => 'official_system',
            'dimension_key' => 'public',
            'dimension_value_key' => 'public',
            'status' => 'pending_location',
            'metadata' => [
                'type_key' => 'village',
                'canonical_name' => 'Reference Village Without Neighborhood',
                'is_pending_base' => true,
            ],
        ]);

        app()->setLocale('fa');
        $presented = app(PendingLocationGroupRequestService::class)
            ->presentationGroups(collect([$request]))->sole();
        $this->assertSame('مجمع عمومی روستای مرجع بدون محله', $presented->name);
        $this->assertSame(
            'Reference Village Without Neighborhood',
            $request->fresh()->metadata['canonical_name'],
            'Rendering must not mutate existing pending request metadata.'
        );

        app()->setLocale('en');
        $english = app(PendingLocationGroupRequestService::class)
            ->presentationGroups(collect([$request->fresh()]))->sole();
        $this->assertStringContainsString('Reference Village Without Neighborhood', $english->name);
    }

    public function test_pending_region_proposal_uses_current_localized_name_instead_of_stale_snapshot(): void
    {
        $schema = LocationFixture::iranSchema();
        $city = LocationFixture::createPath($schema, [
            'country', 'province', 'county', 'section', 'city',
        ])->last();
        $user = User::factory()->create();
        $proposal = LocationProposal::create([
            'parent_location_id' => $city->id,
            'location_schema_id' => $schema->id,
            'country_code' => 'IR',
            'location_type_id' => $schema->types->firstWhere('key', 'urban_region')->id,
            'canonical_name' => 'Reference Pending Region',
            'normalized_name' => 'reference pending region',
            'localized_names' => ['fa' => 'منطقه ۲ ساری'],
            'status' => LocationProposalStatus::Pending,
            'proposer_user_id' => $user->id,
        ]);
        $request = LocationScopedGroupRequest::create([
            'requester_user_id' => $user->id,
            'location_proposal_id' => $proposal->id,
            'scope_kind' => 'official_system',
            'dimension_key' => 'public',
            'dimension_value_key' => 'public',
            'status' => 'pending_location',
            'metadata' => [
                'type_key' => 'urban_region',
                'canonical_name' => 'Reference Pending Region',
                'is_pending_base' => true,
            ],
        ]);

        app()->setLocale('fa');
        $group = app(PendingLocationGroupRequestService::class)
            ->presentationGroups(collect([$request]))->sole();
        $this->assertSame('مجمع عمومی منطقه ۲ ساری', $group->name);
        $this->assertStringNotContainsString('Reference Pending Region', $group->name);
    }
}
