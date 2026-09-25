<?php

namespace Tests\Feature\Admin;

use App\Enums\LocationGovernance\LocationProposalStatus;
use App\Models\GovernanceArea;
use App\Models\Location;
use App\Models\LocationSchema;
use App\Models\User;
use App\Services\LocationGovernance\CommunityAreaService;
use App\Services\LocationGovernance\LocationProposalService;
use App\Services\LocationGovernance\ResidenceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\LocationGovernance\LocationFixture;
use Tests\TestCase;

class LocationGovernanceControlCenterTest extends TestCase
{
    use RefreshDatabase;

    public function test_non_admin_cannot_access_location_governance_control_center(): void
    {
        $user = User::factory()->create(['is_admin' => false]);

        $response = $this->actingAs($user)->get('/admin/location-governance');

        $response->assertRedirect('/home');
    }

    public function test_admin_can_see_pending_proposals_and_review_context(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        [$proposal] = $this->makeProposal('مجتمع نیازمند بررسی');

        $response = $this->actingAs($admin)->get('/admin/location-governance');

        $response->assertOk();
        $response->assertSee('مرکز کنترل مکان و حکمرانی');
        $response->assertSee('مجتمع نیازمند بررسی');
        $response->assertSee('پیشنهادهای در انتظار بررسی');
        $response->assertSee('واردسازی');
        $response->assertSee('نگاشت حکمرانی');
    }

    public function test_admin_displays_persian_location_proposal_reference_and_governance_names_without_rewriting_canonical_edit_fields(): void
    {
        app()->setLocale('fa');
        $admin = User::factory()->create(['is_admin' => true]);
        [$proposal, $parent] = $this->makeProposal('English Pending Complex');
        $proposal->forceFill(['localized_names' => ['fa' => 'مجتمع پیشنهادی فارسی']])->save();
        $parent->forceFill([
            'canonical_name' => 'English Reference Street',
            'localized_names' => ['fa' => 'خیابان مرجع فارسی'],
        ])->save();

        $area = GovernanceArea::factory()->official()->create([
            'key' => 'localized-admin-review-area-'.$parent->id,
            'canonical_name' => 'English Governance Area',
            'localized_names' => ['fa' => 'حوزه حکمرانی فارسی'],
        ]);
        $area->locations()->attach($parent->id);

        $response = $this->actingAs($admin)->get('/admin/location-governance');
        $response->assertOk();
        $response->assertSee('<h3 class="h6 mb-1">مجتمع پیشنهادی فارسی</h3>', false);
        $response->assertSee('والد: خیابان مرجع فارسی');
        $response->assertSee('<td>خیابان مرجع فارسی</td>', false);
        $response->assertSee('<strong>حوزه حکمرانی فارسی</strong>', false);
        // The operator can still edit the canonical source name explicitly;
        // presentation localization must not silently rewrite submitted data.
        $response->assertSee('name="canonical_name"', false);
        $this->assertSame('English Pending Complex', $proposal->fresh()->canonical_name);
        $this->assertSame('English Governance Area', $area->fresh()->canonical_name);
    }

    public function test_admin_sidebar_exposes_location_governance_control_center(): void
    {
        $sidebar = file_get_contents(resource_path('views/admin/partials/sidebar.blade.php'));

        $this->assertIsString($sidebar);
        $this->assertStringContainsString("route('admin.location-governance.index')", $sidebar);
        $this->assertStringContainsString('مکان و حکمرانی', $sidebar);
        $this->assertStringContainsString("request()->routeIs('admin.location-governance.*')", $sidebar);
    }

    public function test_sensitive_review_actions_are_explicit_admin_writes_and_audited(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        [$proposal] = $this->makeProposal('مجتمع تایید انسانی');

        $response = $this->actingAs($admin)->postJson("/admin/location-governance/proposals/{$proposal->id}/approve", [
            'reason' => 'مدارک و شواهد توسط مدیر بررسی شد.',
        ]);

        $response->assertOk()->assertJsonPath('status', LocationProposalStatus::Approved->value);
        $proposal->refresh();
        $this->assertSame(LocationProposalStatus::Approved, $proposal->status);
        $this->assertSame($admin->id, $proposal->reviewed_by_user_id);
        $this->assertTrue(collect($proposal->audit_log ?? [])->contains(
            fn (array $entry) => ($entry['to'] ?? null) === LocationProposalStatus::Approved->value
                && (int) ($entry['actor_user_id'] ?? 0) === $admin->id
        ));
    }

    public function test_non_admin_cannot_execute_sensitive_review_action(): void
    {
        $user = User::factory()->create(['is_admin' => false]);
        [$proposal] = $this->makeProposal('مجتمع حفاظت‌شده');

        $response = $this->actingAs($user)->postJson("/admin/location-governance/proposals/{$proposal->id}/reject", [
            'reason' => 'نباید قابل اجرا باشد.',
        ]);

        $this->assertTrue(in_array($response->status(), [302, 403], true));
        $this->assertSame(LocationProposalStatus::Pending, $proposal->fresh()->status);
    }

    public function test_proposal_queue_filters_open_status_and_shows_latest_audit_context(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $schema = LocationFixture::iranSchema();
        [$needsEvidence] = $this->makeProposal('پیشنهاد نیازمند مدرک', $schema);
        [$pending] = $this->makeProposal('پیشنهاد هنوز در انتظار', $schema);

        app(LocationProposalService::class)->requestMoreEvidence(
            $needsEvidence,
            $admin,
            'مدرک سکونت تکمیلی برای بازبینی لازم است.',
        );

        $response = $this->actingAs($admin)->get('/admin/location-governance?proposal_status=needs_evidence');

        $response->assertOk();
        $response->assertViewHas('proposalStatusFilter', LocationProposalStatus::NeedsEvidence->value);
        $response->assertSee('name="proposal_status"', false);
        $response->assertSee('پیشنهاد نیازمند مدرک');
        $response->assertSee('مدرک سکونت تکمیلی برای بازبینی لازم است.');
        $response->assertDontSee('پیشنهاد هنوز در انتظار');
        $this->assertSame(LocationProposalStatus::Pending, $pending->fresh()->status);
    }

    public function test_control_center_is_composed_from_the_six_focused_operational_partials(): void
    {
        $index = file_get_contents(resource_path('views/admin/location-governance/index.blade.php'));

        $this->assertIsString($index);

        foreach ([
            'proposal-queue',
            'reference-explorer',
            'governance-topology',
            'community-overview',
            'import-diagnostics',
            'health-diagnostics',
        ] as $partial) {
            $this->assertFileExists(resource_path("views/admin/location-governance/partials/{$partial}.blade.php"));
            $this->assertStringContainsString(
                "@include('admin.location-governance.partials.{$partial}')",
                $index,
            );
        }
    }

    public function test_review_action_forms_stack_on_phone_widths_and_expand_from_medium_up(): void
    {
        foreach ([
            'proposal-queue',
            'structure-claim-queue',
        ] as $partial) {
            $source = file_get_contents(resource_path("views/admin/location-governance/partials/{$partial}.blade.php"));

            $this->assertIsString($source);
            $this->assertStringContainsString('d-flex flex-column flex-md-row gap-2', $source);
            $this->assertStringNotContainsString('class="d-flex gap-2"', $source);
        }
    }

    public function test_control_center_exposes_bounded_reference_topology_community_import_and_health_read_models(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $schema = LocationFixture::iranSchema();
        $path = LocationFixture::createPath($schema, [
            'country', 'province', 'county', 'section', 'city', 'urban_region', 'neighborhood', 'street', 'complex',
        ]);
        $neighborhood = $path->firstWhere('level', 'neighborhood');
        $complex = $path->last();

        $official = GovernanceArea::factory()->official()->create([
            'key' => 'task10-official-'.$neighborhood->id,
            'country_code' => 'IR',
            'governance_type' => 'local',
            'canonical_name' => 'حوزه رسمی تست کنترل',
        ]);
        $official->locations()->attach($neighborhood->id);

        // Admin access to the control center must not bypass the same residence-bound
        // community creation policy enforced for every other user. Seed a genuine
        // resident actor for the read-model fixture and keep the admin as reviewer.
        $resident = User::factory()->create();
        app(ResidenceService::class)->setInitialPrimaryResidence($resident, $complex, ['source' => 'control-center-test']);
        $community = app(CommunityAreaService::class)->createFor($complex, $resident);
        $this->makeProposal('پیشنهاد سلامت کنترل', $schema);

        DB::table('location_import_runs')->insert([
            'country_code' => 'IR',
            'source' => 'task10-test',
            'dataset_version' => 'v1',
            'mode' => 'dry-run',
            'status' => 'completed',
            'creates' => 3,
            'updates' => 2,
            'deactivates' => 1,
            'conflicts' => 4,
            'unchanged' => 5,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->actingAs($admin)->get('/admin/location-governance');

        $response->assertOk();
        $response->assertViewHas('referenceLocations', fn ($locations) => $locations->contains('id', $complex->id));
        $response->assertViewHas('officialTopology', fn ($areas) => $areas->contains('id', $official->id));
        $response->assertViewHas('communityAreas', fn ($areas) => $areas->contains('id', $community->id));
        $response->assertViewHas('healthDiagnostics', function ($diagnostics): bool {
            return is_array($diagnostics)
                && array_key_exists('open_proposals', $diagnostics)
                && array_key_exists('above_threshold_proposals', $diagnostics)
                && array_key_exists('pending_residence_intents', $diagnostics)
                && array_key_exists('invalid_pending_residence_intents', $diagnostics)
                && array_key_exists('locations_missing_schema_or_type', $diagnostics)
                && array_key_exists('official_areas_without_location_mapping', $diagnostics);
        });

        foreach ([
            'data-proposal-queue',
            'data-reference-explorer',
            'data-governance-topology',
            'data-community-overview',
            'data-import-diagnostics',
            'data-health-diagnostics',
        ] as $marker) {
            $response->assertSee($marker, false);
        }

        $response->assertSee('حوزه رسمی تست کنترل');
        $response->assertSee($community->canonical_name);
        $response->assertSee('task10-test');
        $response->assertSee('تعارض');
        $response->assertSee('4');
    }

    public function test_health_diagnostics_accepts_reference_settlement_backed_pending_intent(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $user = User::factory()->create();
        $schema = LocationFixture::iranSchema();
        $anchor = LocationFixture::createPath($schema, ['country'])->last();

        $relationship = app(ResidenceService::class)->setInitialPrimaryResidence($user, $anchor, [
            'source' => 'health-reference-settlement-test',
        ]);
        $settlement = \App\Models\ReferenceSettlement::query()->create([
            'source' => 'IranCountryDivisions/geo_1404',
            'dataset_version' => 'v2',
            'external_id' => 'IR-1404-99001',
            'parent_external_id' => 'IR-1404-1',
            'source_code' => '99001',
            'source_row_id' => 99001,
            'name_fa' => 'آبادی سلامت',
            'search_name' => 'آبادی سلامت',
            'classification' => 'unverified_settlement',
            'residential_eligibility' => 'unverified',
            'governance_authorized' => false,
            'operational_promotion_allowed' => false,
            'provenance' => ['source' => 'fixture'],
        ]);
        $claim = \App\Models\ReferenceSettlementResidenceClaim::query()->create([
            'reference_settlement_id' => $settlement->id,
            'user_id' => $user->id,
            'status' => 'pending',
            'submitted_at' => now(),
        ]);
        \App\Models\PendingResidenceIntent::query()->create([
            'user_id' => $user->id,
            'anchor_relationship_id' => $relationship->id,
            'location_proposal_id' => null,
            'reference_settlement_residence_claim_id' => $claim->id,
            'status' => 'pending',
            'selected_at' => now(),
            'metadata' => ['source' => 'fixture'],
        ]);

        $this->actingAs($admin)->get('/admin/location-governance')
            ->assertOk()
            ->assertViewHas('healthDiagnostics', fn ($diagnostics) =>
                $diagnostics['pending_residence_intents'] === 1
                && $diagnostics['invalid_pending_residence_intents'] === 0
            );
    }

    /** @return array{0: \App\Models\LocationProposal, 1: Location} */
    private function makeProposal(string $name, ?LocationSchema $schema = null): array
    {
        $schema ??= LocationFixture::iranSchema();
        $parent = LocationFixture::createPath($schema, [
            'country', 'province', 'county', 'section', 'city', 'urban_region', 'neighborhood', 'street',
        ])->last();
        $type = $schema->types->firstWhere('key', 'complex');

        $proposal = app(LocationProposalService::class)->propose(
            User::factory()->create(),
            $parent,
            $type,
            ['canonical_name' => $name],
        );

        return [$proposal, $parent];
    }
}
