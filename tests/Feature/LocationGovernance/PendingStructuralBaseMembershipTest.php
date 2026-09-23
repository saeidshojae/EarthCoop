<?php

namespace Tests\Feature\LocationGovernance;

use App\Enums\Membership\GroupCreationMode;
use App\Models\GovernanceArea;
use App\Models\Group;
use App\Models\GroupCreationPolicy;
use App\Models\LocationStructureClaim;
use App\Models\MembershipDimension;
use App\Models\User;
use App\Models\UserLocationRelationship;
use App\Services\Groups\CanonicalGroupMembershipReconciler;
use App\Services\Groups\PendingLocationGroupRequestService;
use App\Services\LocationGovernance\LocationStructureClaimService;
use App\Services\Membership\PublicDimensionResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\LocationGovernance\LocationFixture;
use Tests\TestCase;

final class PendingStructuralBaseMembershipTest extends TestCase
{
    use RefreshDatabase;

    public function test_pending_no_neighborhood_base_replaces_only_its_own_canonical_membership_for_region_and_village(): void
    {
        config(['location-governance.groups_enabled' => true]);
        $dimension = MembershipDimension::create([
            'key' => 'public',
            'name' => 'Public',
            'resolver_class' => PublicDimensionResolver::class,
            'enabled' => true,
        ]);
        GroupCreationPolicy::create([
            'membership_dimension_id' => $dimension->id,
            'governance_area_id' => null,
            'mode' => GroupCreationMode::Automatic,
            'enabled' => true,
        ]);
        $schema = LocationFixture::iranSchema();

        foreach ([
            ['country', 'province', 'county', 'section', 'city', 'urban_region'],
            ['country', 'province', 'county', 'section', 'rural_district', 'village'],
        ] as $types) {
            $path = LocationFixture::createPath($schema, $types);
            $parent = $path->get($path->count() - 2);
            $base = $path->last();
            $parentArea = GovernanceArea::create([
                'key' => 'pending-parent-'.$parent->id,
                'country_code' => 'IR',
                'governance_type' => $parent->type->key,
                'area_kind' => 'official',
                'canonical_name' => 'Parent '.$parent->id,
                'rank' => 500,
                'status' => 'active',
            ]);
            $parentArea->locations()->attach($parent->id);
            $baseArea = GovernanceArea::create([
                'key' => 'pending-base-'.$base->id,
                'parent_id' => $parentArea->id,
                'country_code' => 'IR',
                'governance_type' => $base->type->key,
                'area_kind' => 'official',
                'canonical_name' => 'Base '.$base->id,
                'rank' => 700,
                'status' => 'active',
            ]);
            $baseArea->locations()->attach($base->id);

            $user = User::factory()->create();
            $claim = app(LocationStructureClaimService::class)
                ->findOrCreateOpenClaim($base, 'no_neighborhood', $user);
            UserLocationRelationship::create([
                'user_id' => $user->id,
                'location_id' => $base->id,
                'relationship_type' => 'primary_residence',
                'started_at' => now(),
                'metadata' => ['structural_claim_ids' => [$claim->id]],
            ]);

            $reconciler = app(CanonicalGroupMembershipReconciler::class);
            $pending = app(PendingLocationGroupRequestService::class);
            $groups = collect($reconciler->reconcile($user));
            $requests = $pending->openForUser($user);

            // Canonical groups must still exist for audit and later approval.
            $this->assertCount(2, $groups->where('dimension_key', 'public'));
            $baseGroup = $user->groups()->where('governance_area_id', $baseArea->id)
                ->wherePivot('status', 1)->firstOrFail();
            $this->assertSame(0, (int) $baseGroup->pivot->role);
            $this->assertCount(1, $requests->where('dimension_key', 'public'));
            $this->assertSame($claim->id, (int) $requests->first()->location_structure_claim_id);

            // Only presentation deduplicates the observer base against its chosen
            // pending shell. Official ancestor observers must stay visible.
            $visible = $pending->presentableCanonicalGroups(
                $user->groups()->wherePivot('status', 1)->get(),
                $requests,
            );
            $this->assertCount(1, $visible->where('dimension_key', 'public'));
            $this->assertSame($parentArea->id, (int) $visible->first()->governance_area_id);
            $this->assertSame(2, $visible->count() + $pending->presentationGroups($requests)->count());

            // Approval turns the pending base into the same canonical active base;
            // repeated reconciliation must never leave a duplicate pending shell.
            $claim->forceFill(['status' => 'approved'])->save();
            $groups = collect($reconciler->reconcile($user));
            $this->assertCount(2, $groups->where('dimension_key', 'public'));
            $this->assertSame(1, (int) $user->groups()
                ->where('governance_area_id', $baseArea->id)->wherePivot('status', 1)
                ->firstOrFail()->pivot->role);
            $resolvedRequests = $pending->openForUser($user);
            $this->assertCount(0, $resolvedRequests->where('dimension_key', 'public'));
            $this->assertCount(2, $pending->presentableCanonicalGroups(
                $user->groups()->wherePivot('status', 1)->get(),
                $resolvedRequests,
            )->where('dimension_key', 'public'));
        }
    }
}
