<?php

namespace Tests\Feature\Temporal;

use App\Models\AgeGroup;
use App\Models\Group;
use App\Services\Groups\CanonicalGroupMembershipReconciler;
use App\Temporal\Context\TemporalContextResolver;
use App\Temporal\Contracts\TemporalService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\LocationGovernance\MembershipFixture;
use Tests\TestCase;

class ProfileLocalizedBirthDateMembershipSafetyTest extends TestCase
{
    use RefreshDatabase;

    public function test_persian_localized_birth_date_reconciles_age_membership_without_legacy_group_side_effects(): void
    {
        $this->enableStageC();
        ['user' => $user, 'area' => $area] = MembershipFixture::canonicalUser();

        AgeGroup::create(['title' => '35-44', 'min_age' => 35, 'max_age' => 44]);
        app(CanonicalGroupMembershipReconciler::class)->reconcile($user);

        $oldAge = Group::query()
            ->where('governance_area_id', $area->id)
            ->where('dimension_key', 'age')
            ->firstOrFail();
        $legacyGroupCountBefore = Group::query()->whereNull('governance_area_id')->count();

        $targetBirthDate = now()->subYears(40)->toDateString();
        $context = app(TemporalContextResolver::class)->forLocale('fa');
        $localizedBirthDate = app(TemporalService::class)->date($targetBirthDate, $context, 'short');

        $this->actingAs($user)
            ->put(route('profile.update.general'), [
                'birth_date' => $localizedBirthDate,
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $fresh = $user->fresh();
        $this->assertSame($targetBirthDate, substr((string) $fresh->getRawOriginal('birth_date'), 0, 10));
        $this->assertSame(40, $fresh->birth_date->age);

        $newAge = Group::query()
            ->where('governance_area_id', $area->id)
            ->where('dimension_key', 'age')
            ->where('id', '!=', $oldAge->id)
            ->firstOrFail();

        $this->assertSame(1, (int) $user->groups()->whereKey($newAge->id)->firstOrFail()->pivot->status);
        $this->assertSame(0, (int) $user->groups()->whereKey($oldAge->id)->firstOrFail()->pivot->status);
        $this->assertSame(
            $legacyGroupCountBefore,
            Group::query()->whereNull('governance_area_id')->count(),
            'Localized profile birth-date updates must never create legacy age groups.'
        );
    }

    public function test_persian_localized_birth_date_stays_group_dark_while_stage_c_groups_are_disabled(): void
    {
        $this->enableRegistrationOnly();
        ['user' => $user] = MembershipFixture::canonicalUser();

        AgeGroup::create(['title' => '35-44', 'min_age' => 35, 'max_age' => 44]);
        $groupCountBefore = Group::query()->count();
        $legacyGroupCountBefore = Group::query()->whereNull('governance_area_id')->count();

        $targetBirthDate = now()->subYears(40)->toDateString();
        $context = app(TemporalContextResolver::class)->forLocale('fa');
        $localizedBirthDate = app(TemporalService::class)->date($targetBirthDate, $context, 'short');

        $this->actingAs($user)
            ->put(route('profile.update.general'), [
                'birth_date' => $localizedBirthDate,
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $fresh = $user->fresh();
        $this->assertSame($targetBirthDate, substr((string) $fresh->getRawOriginal('birth_date'), 0, 10));
        $this->assertSame(40, $fresh->birth_date->age);
        $this->assertSame($groupCountBefore, Group::query()->count());
        $this->assertSame($legacyGroupCountBefore, Group::query()->whereNull('governance_area_id')->count());
    }

    private function enableRegistrationOnly(): void
    {
        config([
            'location-governance.runtime_enabled' => true,
            'location-governance.registration_enabled' => true,
            'location-governance.groups_enabled' => false,
        ]);
    }

    private function enableStageC(): void
    {
        config([
            'location-governance.runtime_enabled' => true,
            'location-governance.registration_enabled' => true,
            'location-governance.groups_enabled' => true,
        ]);
    }
}
