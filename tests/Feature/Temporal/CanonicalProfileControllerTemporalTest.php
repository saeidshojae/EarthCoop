<?php

namespace Tests\Feature\Temporal;

use App\Http\Controllers\Profile\CanonicalProfileController;
use App\Http\Controllers\Profile\ProfileController;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class CanonicalProfileControllerTemporalTest extends TestCase
{
    use RefreshDatabase;

    public function test_bound_canonical_profile_controller_parses_english_birth_date_parts_as_gregorian(): void
    {
        config()->set('location-governance.registration_enabled', true);
        config()->set('location-governance.groups_enabled', false);

        $user = User::factory()->create([
            'first_name' => 'Ali',
            'last_name' => 'Ahmadi',
            'gender' => 'male',
            'national_id' => null,
            'phone' => null,
            'birth_date' => null,
            'locale' => 'en',
        ]);

        $this->assertInstanceOf(CanonicalProfileController::class, app(ProfileController::class));

        Route::put('/_test/temporal/canonical-profile/general', [ProfileController::class, 'updateGeneral']);

        $this->actingAs($user)
            ->from('/profile/edit')
            ->put('/_test/temporal/canonical-profile/general', [
                'birth_date' => [1, 10, 2026],
            ])
            ->assertRedirect();

        $this->assertSame(
            '2026-10-01',
            substr((string) $user->fresh()->getRawOriginal('birth_date'), 0, 10),
        );
    }
}
