<?php

namespace Tests\Feature\LocationGovernance;

use App\Enums\LocationGovernance\LocationProposalStatus;
use App\Http\Middleware\AdminMiddleware;
use App\Http\Middleware\PermissionMiddleware;
use App\Models\GovernanceArea;
use App\Models\Location;
use App\Models\LocationExternalId;
use App\Models\LocationProposal;
use App\Models\ReferenceSettlement;
use App\Models\ReferenceSettlementResidenceClaim;
use App\Models\User;
use App\Services\LocationGovernance\LocationProposalService;
use App\Services\LocationGovernance\ReferenceSettlementReviewService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\LocationGovernance\LocationFixture;
use Tests\TestCase;

final class IranSettlementNeighborhoodBridgeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'location-governance.runtime_enabled' => true,
            'location-governance.registration_enabled' => true,
            'location-governance.groups_enabled' => false,
            'iran_settlement_catalog.enabled' => true,
            'iran_settlement_catalog.claims_enabled' => true,
        ]);
    }

    /** @return array{0: \App\Models\LocationSchema, 1: Location, 2: ReferenceSettlement} */
    private function scenario(): array
    {
        $schema = LocationFixture::iranSchema();
        $anchor = LocationFixture::createPath($schema, [
            'country', 'province', 'county', 'section', 'rural_district',
        ])->last();
        $anchor->forceFill(['country_code' => 'IR', 'status' => 'active'])->save();

        LocationExternalId::query()->create([
            'location_id' => $anchor->id,
            'source' => 'earthcoop-reference',
            'dataset_version' => 'v1',
            'external_id' => 'IR-UAT-RD',
            'metadata' => ['fixture' => true],
        ]);
        config()->set('iran_v1_v2_crosswalk.mappings', [
            'IR-UAT-RD' => ['v2' => 'IR-1404-1938', 'status' => 'verified_identity'],
        ]);

        $settlement = ReferenceSettlement::query()->create([
            'source' => 'IranCountryDivisions/geo_1404',
            'dataset_version' => 'v2',
            'external_id' => 'IR-1404-99001',
            'parent_external_id' => 'IR-1404-1938',
            'source_code' => '99001',
            'source_row_id' => 99001,
            'name_fa' => 'وری',
            'search_name' => 'وری',
            'classification' => 'unverified_settlement',
            'residential_eligibility' => 'unverified',
            'governance_authorized' => false,
            'operational_promotion_allowed' => false,
            'provenance' => ['source' => 'fixture'],
        ]);

        return [$schema, $anchor, $settlement];
    }

    public function test_settlement_children_endpoint_lists_existing_pending_neighborhood_and_allows_new_one(): void
    {
        [$schema, , $settlement] = $this->scenario();
        $neighborhoodType = $schema->types->firstWhere('key', 'neighborhood');
        $proposal = app(LocationProposalService::class)->proposeUnderReferenceSettlement(
            User::factory()->create(),
            $settlement,
            $neighborhoodType,
            ['canonical_name' => 'محله نمونه'],
        );

        $this->getJson('/location/reference-settlements/'.$settlement->external_id.'/children')
            ->assertOk()
            ->assertJsonPath('reference_settlement.external_id', $settlement->external_id)
            ->assertJsonPath('proposals.0.id', $proposal->id)
            ->assertJsonPath('proposals.0.type_key', 'neighborhood')
            ->assertJsonPath('allowed_types.0.key', 'neighborhood')
            ->assertJsonPath('allowed_types.0.proposal_allowed', true)
            ->assertJsonPath('governance_authorized', false);
    }

    public function test_second_user_reuses_same_pending_neighborhood_instead_of_duplicate(): void
    {
        [$schema, , $settlement] = $this->scenario();
        $type = $schema->types->firstWhere('key', 'neighborhood');
        $service = app(LocationProposalService::class);
        $first = $service->proposeUnderReferenceSettlement(
            User::factory()->create(),
            $settlement,
            $type,
            ['canonical_name' => 'محله مشترک'],
        );
        $second = $service->proposeUnderReferenceSettlement(
            User::factory()->create(),
            $settlement,
            $type,
            ['canonical_name' => 'محله مشترک'],
        );

        $this->assertSame($first->id, $second->id);
        $this->assertDatabaseCount('location_proposals', 1);
    }

    public function test_reference_settlement_neighborhood_cannot_materialize_before_parent_promotion(): void
    {
        [$schema, , $settlement] = $this->scenario();
        $proposal = app(LocationProposalService::class)->proposeUnderReferenceSettlement(
            User::factory()->create(),
            $settlement,
            $schema->types->firstWhere('key', 'neighborhood'),
            ['canonical_name' => 'محله وری'],
        );

        $this->expectException(\DomainException::class);
        app(LocationProposalService::class)->approve(
            $proposal,
            User::factory()->create(),
            'والد هنوز Location رسمی نیست',
        );
    }

    public function test_registration_keeps_settlement_and_neighborhood_in_one_pending_intent_without_governance_materialization(): void
    {
        [$schema, $anchor, $settlement] = $this->scenario();
        $user = User::factory()->create();
        $proposal = app(LocationProposalService::class)->proposeUnderReferenceSettlement(
            $user,
            $settlement,
            $schema->types->firstWhere('key', 'neighborhood'),
            ['canonical_name' => 'محله وری'],
        );
        $locationCount = Location::count();
        $governanceCount = GovernanceArea::count();

        $this->actingAs($user)->post(route('register.step3.process'), [
            'reference_settlement_external_id' => $settlement->external_id,
            'location_proposal_id' => $proposal->id,
        ])->assertRedirect(route('home'))->assertSessionHasNoErrors();

        $relationship = $user->fresh()->locationRelationships()
            ->where('relationship_type', 'primary_residence')->whereNull('ended_at')->sole();
        $claim = ReferenceSettlementResidenceClaim::query()
            ->where('user_id', $user->id)->where('reference_settlement_id', $settlement->id)->sole();
        $intent = \App\Models\PendingResidenceIntent::query()
            ->where('user_id', $user->id)->where('status', 'pending')->sole();

        $this->assertSame($anchor->id, $relationship->location_id);
        $this->assertSame($proposal->id, $intent->location_proposal_id);
        $this->assertSame($claim->id, $intent->reference_settlement_residence_claim_id);
        $this->assertSame($locationCount, Location::count());
        $this->assertSame($governanceCount, GovernanceArea::count());
    }

    public function test_registration_rejects_neighborhood_belonging_to_another_reference_settlement(): void
    {
        [$schema, , $settlement] = $this->scenario();
        $other = $settlement->replicate();
        $other->external_id = 'IR-1404-99002';
        $other->source_code = '99002';
        $other->source_row_id = 99002;
        $other->name_fa = 'آبادی دیگر';
        $other->search_name = 'آبادی دیگر';
        $other->save();

        $proposal = app(LocationProposalService::class)->proposeUnderReferenceSettlement(
            User::factory()->create(),
            $other,
            $schema->types->firstWhere('key', 'neighborhood'),
            ['canonical_name' => 'محله نامرتبط'],
        );
        $user = User::factory()->create();

        $this->actingAs($user)->from(route('register.step3'))->post(route('register.step3.process'), [
            'reference_settlement_external_id' => $settlement->external_id,
            'location_proposal_id' => $proposal->id,
        ])->assertRedirect(route('register.step3'))->assertSessionHasErrors('location_proposal_id');

        $this->assertSame(0, $user->fresh()->locationRelationships()->count());
    }

    public function test_nonresidential_review_cancels_combined_intent_and_rejects_neighborhood(): void
    {
        [$schema, , $settlement] = $this->scenario();
        $user = User::factory()->create();
        $proposal = app(LocationProposalService::class)->proposeUnderReferenceSettlement(
            $user,
            $settlement,
            $schema->types->firstWhere('key', 'neighborhood'),
            ['canonical_name' => 'محله قابل رد'],
        );

        $this->actingAs($user)->post(route('register.step3.process'), [
            'reference_settlement_external_id' => $settlement->external_id,
            'location_proposal_id' => $proposal->id,
        ])->assertRedirect(route('home'));

        app(ReferenceSettlementReviewService::class)->review(
            $settlement,
            User::factory()->create(['is_admin' => true]),
            ReferenceSettlementReviewService::DECISION_NONRESIDENTIAL,
            'منبع رسمی این رکورد را غیرمسکونی معرفی می‌کند.',
            'fixture',
            now()->toDateString(),
            'fixture://settlement',
        );

        $this->assertSame(LocationProposalStatus::Rejected, $proposal->fresh()->status);
        $this->assertSame(
            'cancelled',
            \App\Models\PendingResidenceIntent::query()->where('user_id', $user->id)->latest('id')->firstOrFail()->status
        );
    }

    public function test_admin_health_accepts_valid_combined_pending_intent(): void
    {
        [$schema, , $settlement] = $this->scenario();
        $user = User::factory()->create();
        $proposal = app(LocationProposalService::class)->proposeUnderReferenceSettlement(
            $user,
            $settlement,
            $schema->types->firstWhere('key', 'neighborhood'),
            ['canonical_name' => 'محله سلامت'],
        );
        $this->actingAs($user)->post(route('register.step3.process'), [
            'reference_settlement_external_id' => $settlement->external_id,
            'location_proposal_id' => $proposal->id,
        ])->assertRedirect(route('home'));

        $admin = User::factory()->create(['is_admin' => true]);
        $this->withoutMiddleware([AdminMiddleware::class, PermissionMiddleware::class]);
        $this->actingAs($admin)->get('/admin/location-governance')
            ->assertOk()
            ->assertViewHas('healthDiagnostics', fn ($diagnostics) =>
                $diagnostics['pending_residence_intents'] === 1
                && $diagnostics['invalid_pending_residence_intents'] === 0
            );
    }

    public function test_migration_rollback_fails_closed_while_reference_settlement_children_exist(): void
    {
        $source = file_get_contents(database_path(
            'migrations/2026_09_24_000006_link_location_proposals_to_reference_settlements.php'
        ));
        $down = \Illuminate\Support\Str::after($source, 'public function down(): void');

        $guard = "whereNotNull('parent_reference_settlement_id')";
        $drop = "dropColumn('parent_reference_settlement_id')";
        $this->assertStringContainsString($guard, $down);
        $this->assertStringContainsString('throw new RuntimeException', $down);
        $this->assertLessThan(strpos($down, $drop), strpos($down, $guard));
    }
}
