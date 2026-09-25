<?php

namespace Tests\Feature\LocationGovernance;

use App\Services\LocationGovernance\LocationProposalService;
use App\Services\LocationGovernance\ResidenceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\LocationGovernance\MembershipFixture;
use Tests\TestCase;

class MyLocationGovernancePendingSyncTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_counts_pending_base_and_memberships_from_same_contract_as_my_groups(): void
    {
        config([
            'location-governance.runtime_enabled' => true,
            'location-governance.groups_enabled' => true,
        ]);

        ['user' => $user, 'endpoint' => $city] = MembershipFixture::canonicalUser();
        $schema = $city->schema()->firstOrFail();

        $region = app(LocationProposalService::class)->propose(
            $user,
            $city,
            $schema->types()->where('key', 'urban_region')->firstOrFail(),
            ['canonical_name' => '۵ ساری'],
        );
        $neighborhood = app(LocationProposalService::class)->proposeUnderProposal(
            $user,
            $region,
            $schema->types()->where('key', 'neighborhood')->firstOrFail(),
            ['canonical_name' => 'آزمایشی ۲'],
        );
        app(ResidenceService::class)->setPendingResidenceIntent($user, $neighborhood);

        $response = $this->actingAs($user)->get(route('location-governance.me'));

        $response->assertOk()
            ->assertViewHas('governanceLevelCount', 3)
            ->assertViewHas('pendingGovernanceProposals', fn ($items) => $items->pluck('id')->all() === [$neighborhood->id, $region->id])
            ->assertViewHas('membershipsByDimension', function ($buckets): bool {
                $active = collect($buckets)->sum(fn ($bucket) => collect($bucket->get('active', []))->count());
                $observer = collect($buckets)->sum(fn ($bucket) => collect($bucket->get('observer', []))->count());

                return $active === 5 && $observer === 10;
            });

        $response->assertSee('آزمایشی ۲')
            ->assertSee('در انتظار تأیید');

        // The UI presents counts per dimension (1 active + 2 observers in each
        // of five dimensions), while the aggregate contract above proves 5/10.
        $response->assertSee('1 فعال')
            ->assertSee('2 ناظر');
    }
}
