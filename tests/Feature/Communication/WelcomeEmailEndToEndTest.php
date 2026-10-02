<?php

namespace Tests\Feature\Communication;

use App\Events\RegistrationCompleted;
use App\Models\Communication;
use App\Models\LocationExternalId;
use App\Models\ReferenceSettlement;
use App\Models\User;
use App\Services\LocationGovernance\LocationProposalService;
use App\Services\LocationGovernance\LocationStructureClaimService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\Support\LocationGovernance\LocationFixture;
use Tests\TestCase;

final class WelcomeEmailEndToEndTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'location-governance.runtime_enabled' => true,
            'location-governance.registration_enabled' => true,
            'iran_settlement_catalog.enabled' => true,
            'iran_settlement_catalog.claims_enabled' => true,
        ]);
    }

    public function test_active_canonical_registration_emits_completion_event_after_success(): void
    {
        Event::fake([RegistrationCompleted::class]);
        $schema = LocationFixture::iranSchema();
        $location = LocationFixture::createPath($schema, [
            'country', 'province', 'county', 'section', 'city', 'urban_region', 'neighborhood',
        ])->last();
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('register.step3.process'), [
            'location_id' => $location->id,
        ])->assertRedirect(route('home'));

        Event::assertDispatched(RegistrationCompleted::class, function ($event) use ($user): bool {
            return $event->userId === $user->id
                && $event->locale === 'fa'
                && $event->completedAt !== '';
        });
    }

    public function test_pending_proposal_registration_emits_completion_event_only_after_pending_residence_is_persisted(): void
    {
        Event::fake([RegistrationCompleted::class]);
        $schema = LocationFixture::iranSchema();
        $city = LocationFixture::createPath($schema, [
            'country', 'province', 'county', 'section', 'city',
        ])->last();
        $user = User::factory()->create();
        $region = app(LocationProposalService::class)->propose(
            $user,
            $city,
            $schema->types->firstWhere('key', 'urban_region'),
            ['canonical_name' => 'منطقه در انتظار خوش‌آمد'],
        );
        $neighborhood = app(LocationProposalService::class)->proposeUnderProposal(
            $user,
            $region,
            $schema->types->firstWhere('key', 'neighborhood'),
            ['canonical_name' => 'محله در انتظار خوش‌آمد'],
        );

        $this->actingAs($user)->post(route('register.step3.process'), [
            'location_proposal_id' => $neighborhood->id,
        ])->assertRedirect(route('home'));

        $this->assertDatabaseHas('pending_residence_intents', [
            'user_id' => $user->id,
            'location_proposal_id' => $neighborhood->id,
        ]);
        Event::assertDispatched(RegistrationCompleted::class, fn ($event): bool => $event->userId === $user->id);
    }

    public function test_reference_settlement_registration_emits_completion_event_after_claim_and_residence_are_persisted(): void
    {
        Event::fake([RegistrationCompleted::class]);
        $schema = LocationFixture::iranSchema();
        $anchor = LocationFixture::createPath($schema, [
            'country', 'province', 'county', 'section', 'rural_district',
        ])->last();
        LocationExternalId::query()->create([
            'location_id' => $anchor->id,
            'source' => 'earthcoop-reference',
            'dataset_version' => 'v2',
            'external_id' => 'IR-1404-5555',
            'metadata' => ['fixture' => true],
        ]);
        $settlement = ReferenceSettlement::query()->create([
            'source' => 'IranCountryDivisions/geo_1404',
            'dataset_version' => 'v2',
            'external_id' => 'IR-1404-88001',
            'parent_external_id' => 'IR-1404-5555',
            'source_code' => '88001',
            'source_row_id' => 88001,
            'name_fa' => 'آبادی خوش‌آمد نمونه',
            'search_name' => 'آبادی خوش‌آمد نمونه',
            'classification' => 'unverified_settlement',
            'residential_eligibility' => 'unverified',
            'governance_authorized' => false,
            'operational_promotion_allowed' => false,
            'provenance' => ['source' => 'fixture'],
        ]);
        $user = User::factory()->create();
        $claim = app(LocationStructureClaimService::class)
            ->findOrCreateOpenReferenceSettlementClaim($settlement, 'no_neighborhood', $user);

        $this->actingAs($user)->post(route('register.step3.process'), [
            'reference_settlement_external_id' => $settlement->external_id,
            'location_structure_claim_ids' => [$claim->id],
        ])->assertRedirect(route('home'));

        $this->assertDatabaseHas('reference_settlement_residence_claims', [
            'reference_settlement_id' => $settlement->id,
            'user_id' => $user->id,
        ]);
        Event::assertDispatched(RegistrationCompleted::class, fn ($event): bool => $event->userId === $user->id);
    }

    public function test_repeated_successful_submit_emits_completion_event_only_once(): void
    {
        Event::fake([RegistrationCompleted::class]);
        $schema = LocationFixture::iranSchema();
        $location = LocationFixture::createPath($schema, [
            'country', 'province', 'county', 'section', 'city', 'urban_region', 'neighborhood',
        ])->last();
        $user = User::factory()->create();
        $payload = ['location_id' => $location->id];

        $this->actingAs($user)->post(route('register.step3.process'), $payload)->assertRedirect(route('home'));
        $this->actingAs($user)->post(route('register.step3.process'), $payload)->assertRedirect(route('home'));

        Event::assertDispatchedTimes(RegistrationCompleted::class, 1);
    }

    public function test_welcome_context_builder_exposes_only_canonical_member_fields(): void
    {
        $user = User::factory()->create([
            'first_name' => 'مریم',
            'last_name' => 'کاربر',
            'email' => 'context-user@example.test',
        ]);

        $context = app(\App\Services\Communication\Context\WelcomeCommunicationContextBuilder::class)
            ->build($user);

        $this->assertSame(['display_name', 'email', 'profile_url'], array_keys($context));
        $this->assertSame('مریم کاربر', $context['display_name']);
        $this->assertSame($user->email, $context['email']);
        $this->assertSame(route('profile.show'), $context['profile_url']);
    }

    public function test_registration_completed_listener_creates_one_personalized_welcome_communication(): void
    {
        $user = User::factory()->create([
            'first_name' => 'سارا',
            'last_name' => 'نمونه',
            'email' => 'welcome-user@example.test',
        ]);
        $event = new RegistrationCompleted($user->id, '2026-09-30T10:00:00+03:30', 'fa');

        event($event);
        event($event);

        $this->assertDatabaseCount('communications', 1);
        $communication = Communication::query()->sole();
        $this->assertSame('registration.completed', $communication->source_id);
        $this->assertSame('سارا نمونه', $communication->context_snapshot['display_name'] ?? null);
        $this->assertSame($user->email, $communication->recipients()->sole()->email);
    }
}
