<?php

namespace Tests\Feature\Temporal;

use App\Models\AgeGroup;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CanonicalProfileBirthDateTemporalTest extends TestCase
{
    use RefreshDatabase;

    public function test_equivalent_persian_and_english_birth_date_parts_store_same_canonical_date(): void
    {
        config()->set('location-governance.registration_enabled', true);
        config()->set('location-governance.groups_enabled', false);

        $persianUser = User::factory()->create([
            'first_name' => 'علی',
            'last_name' => 'احمدی',
            'gender' => 'male',
            'national_id' => null,
            'phone' => null,
            'birth_date' => null,
            'locale' => 'fa',
        ]);

        $this->actingAs($persianUser)
            ->from('/profile/edit')
            ->put(route('profile.update.general'), [
                'birth_date' => [9, 7, 1405],
            ])
            ->assertRedirect();

        $persianCanonical = $persianUser->fresh()->getRawOriginal('birth_date');

        $englishUser = User::factory()->create([
            'first_name' => 'علی',
            'last_name' => 'احمدی',
            'gender' => 'male',
            'national_id' => null,
            'phone' => null,
            'birth_date' => null,
            'locale' => 'en',
        ]);

        $this->actingAs($englishUser)
            ->from('/profile/edit')
            ->put(route('profile.update.general'), [
                'birth_date' => [1, 10, 2026],
            ])
            ->assertRedirect();

        $englishCanonical = $englishUser->fresh()->getRawOriginal('birth_date');

        $this->assertSame('2026-10-01', substr((string) $persianCanonical, 0, 10));
        $this->assertSame($persianCanonical, $englishCanonical);
    }

    public function test_legacy_profile_fallback_parses_birth_date_parts_using_user_locale(): void
    {
        config()->set('location-governance.registration_enabled', false);
        config()->set('location-governance.groups_enabled', false);

        AgeGroup::create([
            'title' => 'همه سنین تست',
            'min_age' => 0,
            'max_age' => 200,
        ]);

        $persianUser = User::factory()->create([
            'first_name' => 'علی',
            'last_name' => 'احمدی',
            'gender' => 'male',
            'national_id' => null,
            'phone' => null,
            'birth_date' => null,
            'locale' => 'fa',
        ]);

        $this->actingAs($persianUser)
            ->from('/profile/edit')
            ->put(route('profile.update.general'), [
                'birth_date' => [9, 7, 1405],
            ])
            ->assertRedirect();

        $persianCanonical = $persianUser->fresh()->getRawOriginal('birth_date');

        $englishUser = User::factory()->create([
            'first_name' => 'علی',
            'last_name' => 'احمدی',
            'gender' => 'male',
            'national_id' => null,
            'phone' => null,
            'birth_date' => null,
            'locale' => 'en',
        ]);

        $this->actingAs($englishUser)
            ->from('/profile/edit')
            ->put(route('profile.update.general'), [
                'birth_date' => [1, 10, 2026],
            ])
            ->assertRedirect();

        $englishCanonical = $englishUser->fresh()->getRawOriginal('birth_date');

        $this->assertSame('2026-10-01', substr((string) $persianCanonical, 0, 10));
        $this->assertSame($persianCanonical, $englishCanonical);
    }
}
