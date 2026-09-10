<?php

namespace Tests\Feature\LocationGovernance;

use App\Enums\LocationGovernance\LocationProposalStatus;
use App\Models\Location;
use App\Models\LocationProposal;
use App\Models\User;
use App\Services\LocationGovernance\LocationProposalService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\LocationGovernance\LocationFixture;
use Tests\TestCase;

class LocationProposalWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_existing_canonical_location_is_reused_before_a_new_proposal_is_created(): void
    {
        $schema = LocationFixture::iranSchema();
        $parent = LocationFixture::createPath($schema, [
            'country', 'province', 'county', 'section', 'city', 'urban_region', 'neighborhood', 'street',
        ])->last();
        $type = $schema->types->firstWhere('key', 'complex');
        $existing = Location::factory()->create([
            'parent_id' => $parent->id,
            'location_schema_id' => $schema->id,
            'location_type_id' => $type->id,
            'country_code' => 'IR',
            'name' => 'مجتمع موجود',
            'canonical_name' => 'مجتمع موجود',
            'level' => 'complex',
            'status' => 'active',
        ]);

        $result = app(LocationProposalService::class)->propose(
            User::factory()->create(),
            $parent,
            $type,
            ['canonical_name' => '  مجتمع   موجود  '],
        );

        $this->assertInstanceOf(Location::class, $result);
        $this->assertSame($existing->id, $result->id);
        $this->assertSame(0, LocationProposal::query()->count());
    }

    public function test_same_pending_candidate_is_reused_instead_of_creating_duplicate_proposals(): void
    {
        $schema = LocationFixture::iranSchema();
        $parent = LocationFixture::createPath($schema, [
            'country', 'province', 'county', 'section', 'city', 'urban_region', 'neighborhood', 'street',
        ])->last();
        $type = $schema->types->firstWhere('key', 'complex');
        $service = app(LocationProposalService::class);

        $first = $service->propose(User::factory()->create(), $parent, $type, [
            'canonical_name' => 'مجتمع ارغوان',
        ]);
        $second = $service->propose(User::factory()->create(), $parent, $type, [
            'canonical_name' => '  مجتمع   ارغوان ',
        ]);

        $this->assertSame($first->id, $second->id);
        $this->assertSame(1, $first->newQuery()->count());
        $this->assertSame(LocationProposalStatus::Pending, $second->status);
    }

    public function test_review_transitions_are_audited_and_approval_materializes_the_location(): void
    {
        $schema = LocationFixture::iranSchema();
        $parent = LocationFixture::createPath($schema, [
            'country', 'province', 'county', 'section', 'city', 'urban_region', 'neighborhood', 'street',
        ])->last();
        $type = $schema->types->firstWhere('key', 'complex');
        $proposer = User::factory()->create();
        $reviewer = User::factory()->create();
        $service = app(LocationProposalService::class);

        $proposal = $service->propose($proposer, $parent, $type, [
            'canonical_name' => 'مجتمع تاییدشدنی',
            'localized_names' => ['fa' => 'مجتمع تاییدشدنی'],
        ]);

        $service->requestMoreEvidence($proposal, $reviewer, 'نشانی دقیق‌تر لازم است.');
        $this->assertSame(LocationProposalStatus::NeedsEvidence, $proposal->fresh()->status);

        $service->support($proposal->fresh(), User::factory()->create(), ['note' => 'evidence supplied']);
        $approvedLocation = $service->approve($proposal->fresh(), $reviewer, 'مدارک کافی است.');

        $proposal->refresh();
        $this->assertInstanceOf(Location::class, $approvedLocation);
        $this->assertSame(LocationProposalStatus::Approved, $proposal->status);
        $this->assertSame($approvedLocation->id, $proposal->resolved_location_id);
        $this->assertNotNull($proposal->approved_at);
        $this->assertSame($reviewer->id, $proposal->reviewed_by_user_id);

        $audit = collect($proposal->audit_log ?? []);
        $this->assertTrue($audit->contains(fn (array $entry) => ($entry['to'] ?? null) === LocationProposalStatus::NeedsEvidence->value));
        $this->assertTrue($audit->contains(fn (array $entry) => ($entry['to'] ?? null) === LocationProposalStatus::Approved->value));
    }

    public function test_reject_and_merge_transitions_are_explicit_and_audited(): void
    {
        $schema = LocationFixture::iranSchema();
        $parent = LocationFixture::createPath($schema, [
            'country', 'province', 'county', 'section', 'city', 'urban_region', 'neighborhood', 'street',
        ])->last();
        $type = $schema->types->firstWhere('key', 'complex');
        $reviewer = User::factory()->create();
        $service = app(LocationProposalService::class);

        $rejected = $service->propose(User::factory()->create(), $parent, $type, ['canonical_name' => 'رد شونده']);
        $service->reject($rejected, $reviewer, 'مکان قابل اثبات نیست.');
        $this->assertSame(LocationProposalStatus::Rejected, $rejected->fresh()->status);

        $existing = Location::factory()->create([
            'parent_id' => $parent->id,
            'location_schema_id' => $schema->id,
            'location_type_id' => $type->id,
            'country_code' => 'IR',
            'name' => 'مکان موجود',
            'canonical_name' => 'مکان موجود',
            'level' => 'complex',
            'status' => 'active',
        ]);
        $merged = $service->propose(User::factory()->create(), $parent, $type, ['canonical_name' => 'نام تکراری دیگر']);
        $service->merge($merged, $existing, $reviewer, 'همان مکان موجود است.');

        $merged->refresh();
        $this->assertSame(LocationProposalStatus::Merged, $merged->status);
        $this->assertSame($existing->id, $merged->resolved_location_id);
        $this->assertTrue(collect($merged->audit_log ?? [])->contains(
            fn (array $entry) => ($entry['to'] ?? null) === LocationProposalStatus::Merged->value
        ));
    }
}
