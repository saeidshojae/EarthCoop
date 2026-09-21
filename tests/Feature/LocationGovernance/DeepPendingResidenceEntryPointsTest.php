<?php

namespace Tests\Feature\LocationGovernance;

use App\Http\Middleware\AdminMiddleware;
use App\Http\Middleware\PermissionMiddleware;
use App\Models\User;
use App\Models\UserExperience;
use App\Services\LocationGovernance\LocationProposalService;
use App\Services\LocationGovernance\ResidenceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\Support\LocationGovernance\LocationFixture;
use Tests\TestCase;

class DeepPendingResidenceEntryPointsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'location-governance.runtime_enabled' => true,
            'location-governance.registration_enabled' => true,
            'location-governance.groups_enabled' => false,
        ]);

        $this->withoutMiddleware([
            AdminMiddleware::class,
            PermissionMiddleware::class,
        ]);
    }

    public function test_password_login_does_not_send_canonical_member_without_legacy_address_back_to_step3(): void
    {
        $user = User::factory()->create([
            'password' => Hash::make('known-password'),
            'national_id' => 'T' . fake()->unique()->numerify('#########'),
        ]);
        $experience = \App\Models\ExperienceField::query()->create([
            'name' => 'Login canonical experience',
            'status' => 1,
        ]);
        \Illuminate\Support\Facades\DB::table('user_experience_field')->insert([
            'user_id' => $user->id,
            'experience_field_id' => $experience->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $schema = LocationFixture::iranSchema();
        $residence = LocationFixture::createPath(
            $schema,
            ['country', 'province', 'county', 'section', 'city']
        )->last();
        app(ResidenceService::class)->setInitialPrimaryResidence($user, $residence, ['source' => 'test']);

        $this->assertDatabaseMissing('addresses', ['user_id' => $user->id]);

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'known-password',
        ])->assertRedirect('/home');
    }

    public function test_google_completion_gate_accepts_canonical_residence_without_legacy_address(): void
    {
        $user = User::factory()->create([
            'national_id' => 'G' . fake()->unique()->numerify('#########'),
            'password' => Hash::make('known-password'),
        ]);
        $experience = \App\Models\ExperienceField::query()->create([
            'name' => 'Google canonical experience',
            'status' => 1,
        ]);
        \Illuminate\Support\Facades\DB::table('user_experience_field')->insert([
            'user_id' => $user->id,
            'experience_field_id' => $experience->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $schema = LocationFixture::iranSchema();
        $residence = LocationFixture::createPath(
            $schema,
            ['country', 'province', 'county', 'section', 'city']
        )->last();
        app(ResidenceService::class)->setInitialPrimaryResidence($user, $residence, ['source' => 'test']);

        $this->assertDatabaseMissing('addresses', ['user_id' => $user->id]);

        $controller = app(\App\Http\Controllers\Auth\GoogleController::class);
        $method = new \ReflectionMethod($controller, 'getIncompleteStep');
        $method->setAccessible(true);

        $this->assertNull($method->invoke($controller, $user));
    }

    public function test_profile_reputation_dry_run_counts_canonical_member_without_legacy_address(): void
    {
        $user = User::factory()->create([
            'first_name' => 'Canonical',
            'last_name' => 'Member',
            'gender' => 'male',
            'national_id' => 'R' . fake()->unique()->numerify('#########'),
            'phone' => '09' . fake()->unique()->numerify('#########'),
        ]);
        $experience = \App\Models\ExperienceField::query()->create([
            'name' => 'Reputation canonical experience',
            'status' => 1,
        ]);
        \Illuminate\Support\Facades\DB::table('user_experience_field')->insert([
            'user_id' => $user->id,
            'experience_field_id' => $experience->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $schema = LocationFixture::iranSchema();
        $residence = LocationFixture::createPath(
            $schema,
            ['country', 'province', 'county', 'section', 'city']
        )->last();
        app(ResidenceService::class)->setInitialPrimaryResidence($user, $residence, ['source' => 'test']);

        $this->assertDatabaseMissing('addresses', ['user_id' => $user->id]);

        $this->artisan('reputation:backfill-profile', ['--dry-run' => true])
            ->expectsOutputToContain('Eligible users: 1')
            ->assertSuccessful();
    }

    public function test_registration_rejects_deepest_pending_micro_proposal_while_profile_and_admin_remain_deep_capable(): void
    {
        [$user, , $deepest] = $this->makeDeepProposalScenario();

        $response = $this->actingAs($user)
            ->from(route('register.step3'))
            ->post(route('register.step3.process'), [
                'location_proposal_id' => $deepest->id,
            ]);

        $response->assertRedirect(route('register.step3'));
        $response->assertSessionHasErrors('location_proposal_id');
        $this->assertSame(0, $user->fresh()->locationRelationships()->count());
        $this->assertSame(0, $user->fresh()->pendingResidenceIntents()->count());
    }

    public function test_profile_accepts_deepest_pending_proposal_without_transferring_from_canonical_anchor(): void
    {
        [$user, $anchor, $deepest] = $this->makeDeepProposalScenario();
        app(ResidenceService::class)->setInitialPrimaryResidence($user, $anchor, ['source' => 'test']);

        $response = $this->actingAs($user)->put(route('profile.update.address'), [
            'location_proposal_id' => $deepest->id,
        ]);

        $response->assertSessionHasNoErrors();

        $current = $user->fresh()->locationRelationships()
            ->where('relationship_type', 'primary_residence')
            ->whereNull('ended_at')
            ->sole();
        $intent = $user->fresh()->pendingResidenceIntents()
            ->where('status', 'pending')
            ->sole();

        $this->assertSame($anchor->id, $current->location_id);
        $this->assertFalse((bool) $current->explicit_transfer);
        $this->assertSame($deepest->id, $intent->location_proposal_id);
        $this->assertSame($current->id, $intent->anchor_relationship_id);
    }

    public function test_admin_accepts_deepest_pending_proposal_and_preserves_audit_metadata(): void
    {
        [$target, $anchor, $deepest] = $this->makeDeepProposalScenario();
        $admin = User::factory()->create(['is_admin' => true]);
        app(ResidenceService::class)->setInitialPrimaryResidence($target, $anchor, ['source' => 'test']);
        $reason = 'ثبت جزئیات عمیق محل سکونت توسط مدیر';

        $response = $this->actingAs($admin)
            ->from(route('admin.users.edit', $target))
            ->put(route('admin.users.residence.update', $target), [
                'location_proposal_id' => $deepest->id,
                'reason' => $reason,
            ]);

        $response->assertRedirect(route('admin.users.edit', $target));
        $response->assertSessionHasNoErrors();

        $current = $target->fresh()->locationRelationships()
            ->where('relationship_type', 'primary_residence')
            ->whereNull('ended_at')
            ->sole();
        $intent = $target->fresh()->pendingResidenceIntents()
            ->where('status', 'pending')
            ->sole();

        $this->assertSame($anchor->id, $current->location_id);
        $this->assertSame($deepest->id, $intent->location_proposal_id);
        $this->assertSame($admin->id, data_get($intent->metadata, 'actor_user_id'));
        $this->assertSame($reason, data_get($intent->metadata, 'reason'));
    }

    private function makeDeepProposalScenario(): array
    {
        $schema = LocationFixture::iranSchema();
        $anchor = LocationFixture::createPath($schema, [
            'country', 'province', 'county', 'section', 'city', 'urban_region', 'neighborhood',
        ])->last();
        $streetType = $schema->types->firstWhere('key', 'street');
        $alleyType = $schema->types->firstWhere('key', 'alley');
        $user = User::factory()->create();
        $service = app(LocationProposalService::class);
        $street = $service->propose($user, $anchor, $streetType, [
            'canonical_name' => 'خیابان پیشنهادی عمیق',
        ]);
        $alley = $service->proposeUnderProposal($user, $street, $alleyType, [
            'canonical_name' => 'کوچه پیشنهادی عمیق',
        ]);

        return [$user, $anchor, $alley];
    }
}
