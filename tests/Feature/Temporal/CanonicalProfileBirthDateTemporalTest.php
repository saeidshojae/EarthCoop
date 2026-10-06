<?php

namespace Tests\Feature\Temporal;

use App\Models\AgeGroup;
use App\Models\User;
use App\Temporal\Context\TemporalContextResolver;
use App\Temporal\Contracts\TemporalService;
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

    public function test_canonical_profile_accepts_localized_birth_date_strings(): void
    {
        config()->set('location-governance.registration_enabled', true);
        config()->set('location-governance.groups_enabled', false);

        $persianUser = User::factory()->create(['birth_date' => null, 'locale' => 'fa']);
        $persianContext = app(TemporalContextResolver::class)->forUser($persianUser);
        $this->assertSame(
            '2026-10-01',
            app(TemporalService::class)->parseDate('1405/07/09', $persianContext)->toCanonical(),
            'The Jalali parser and persisted Persian user context must agree before HTTP middleware/controller handling.',
        );

        $this->actingAs($persianUser)
            ->from('/profile/edit')
            ->put(route('profile.update.general'), ['birth_date' => '1405/07/09'])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $englishUser = User::factory()->create(['birth_date' => null, 'locale' => 'en']);
        $englishContext = app(TemporalContextResolver::class)->forUser($englishUser);
        $this->assertSame(
            '2026-10-01',
            app(TemporalService::class)->parseDate('2026-10-01', $englishContext)->toCanonical(),
            'The Gregorian parser and persisted English user context must agree before HTTP middleware/controller handling.',
        );

        $this->actingAs($englishUser)
            ->from('/profile/edit')
            ->put(route('profile.update.general'), ['birth_date' => '2026-10-01'])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertSame('2026-10-01', substr((string) $persianUser->fresh()->getRawOriginal('birth_date'), 0, 10));
        $this->assertSame('2026-10-01', substr((string) $englishUser->fresh()->getRawOriginal('birth_date'), 0, 10));
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

    public function test_legacy_profile_fallback_accepts_localized_birth_date_strings(): void
    {
        config()->set('location-governance.registration_enabled', false);
        config()->set('location-governance.groups_enabled', false);

        AgeGroup::create([
            'title' => 'همه سنین تست',
            'min_age' => 0,
            'max_age' => 200,
        ]);

        $persianUser = User::factory()->create(['birth_date' => null, 'locale' => 'fa']);
        $this->actingAs($persianUser)
            ->from('/profile/edit')
            ->put(route('profile.update.general'), ['birth_date' => '1405/07/09'])
            ->assertRedirect();

        $englishUser = User::factory()->create(['birth_date' => null, 'locale' => 'en']);
        $this->actingAs($englishUser)
            ->from('/profile/edit')
            ->put(route('profile.update.general'), ['birth_date' => '2026-10-01'])
            ->assertRedirect();

        $this->assertSame('2026-10-01', substr((string) $persianUser->fresh()->getRawOriginal('birth_date'), 0, 10));
        $this->assertSame('2026-10-01', substr((string) $englishUser->fresh()->getRawOriginal('birth_date'), 0, 10));
    }

    public function test_profile_birth_date_field_uses_temporal_input_component(): void
    {
        $view = file_get_contents(resource_path('views/profile/partials/general.blade.php'));

        $this->assertStringContainsString('<x-temporal.date-input', $view);
        $this->assertStringNotContainsString('Morilog\\Jalali', $view);
        $this->assertStringNotContainsString('Jalalian::', $view);
        $this->assertStringNotContainsString('name="birth_date[]"', $view);
    }
}
