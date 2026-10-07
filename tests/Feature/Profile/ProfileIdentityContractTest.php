<?php

namespace Tests\Feature\Profile;

use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ProfileIdentityContractTest extends TestCase
{
    use RefreshDatabase;

    public function test_national_id_is_database_unique(): void
    {
        User::factory()->create(['national_id' => '0013546880']);

        $this->expectException(QueryException::class);
        User::factory()->create(['national_id' => '0013546880']);
    }

    public function test_profile_page_has_phone_country_catalog_and_no_legacy_birth_date_picker(): void
    {
        config()->set('location-governance.registration_enabled', true);

        $user = User::factory()->create([
            'birth_date' => '1989-04-10',
            'national_id' => '0013546880',
            'phone' => '9123456789',
            'phone_country_code' => '+98',
            'edited' => false,
        ]);

        $response = $this->actingAs($user)->get('/profile/edit');

        $response->assertOk();
        $response->assertSee('🇮🇷', false);
        $response->assertSee('+98', false);

        $view = file_get_contents(resource_path('views/profile/edit.blade.php'));
        $this->assertStringNotContainsString("$('#birth_date').persianDatepicker(", $view);
        $this->assertStringNotContainsString('persian-datepicker.js', $view);
        $this->assertStringContainsString("select:not(#country_code)", $view);

        $general = file_get_contents(resource_path('views/profile/partials/general.blade.php'));
        $this->assertStringContainsString("{{ \$country['flag'] }} {{ \$country['name'] }} ({{ \$country['code'] }})", $general);
    }

    public function test_email_and_national_id_cannot_be_changed_from_profile(): void
    {
        config()->set('location-governance.registration_enabled', true);
        config()->set('location-governance.groups_enabled', false);

        $user = User::factory()->create([
            'email' => 'fixed@example.test',
            'national_id' => '0013546880',
            'edited' => false,
        ]);

        $this->actingAs($user)
            ->from('/profile/edit')
            ->put(route('profile.update.general'), [
                'email' => 'changed@example.test',
                'national_id' => '1234567891',
                'nickname' => 'سعید',
            ])
            ->assertRedirect('/profile/edit')
            ->assertSessionHasErrors(['email', 'national_id']);

        $fresh = $user->fresh();
        $this->assertSame('fixed@example.test', $fresh->email);
        $this->assertSame('0013546880', $fresh->national_id);
    }

    public function test_identity_fields_can_change_once_but_nickname_remains_editable(): void
    {
        config()->set('location-governance.registration_enabled', true);
        config()->set('location-governance.groups_enabled', false);

        $user = User::factory()->create([
            'first_name' => 'علی',
            'last_name' => 'احمدی',
            'birth_date' => '1990-01-01',
            'gender' => 'male',
            'national_id' => '0013546880',
            'phone_country_code' => '+98',
            'phone' => '9123456789',
            'edited' => false,
            'identity_edit_used_at' => null,
        ]);

        $this->actingAs($user)
            ->from('/profile/edit')
            ->put(route('profile.update.general'), [
                'first_name' => 'سعید',
                'last_name' => 'شجاعی',
                'birth_date' => '1368/01/21',
                'gender' => 'male',
                'country_code' => '+98',
                'phone' => '9394765289',
                'nickname' => 'سعید جان',
            ])
            ->assertRedirect('/profile/edit')
            ->assertSessionHasNoErrors();

        $fresh = $user->fresh();
        $this->assertSame('سعید', $fresh->first_name);
        $this->assertSame('9394765289', $fresh->phone);
        $this->assertSame('+98', $fresh->phone_country_code);
        $this->assertNotNull($fresh->identity_edit_used_at);

        $this->actingAs($fresh)
            ->from('/profile/edit')
            ->put(route('profile.update.general'), [
                'first_name' => 'محمد',
                'nickname' => 'نام تازه',
            ])
            ->assertRedirect('/profile/edit')
            ->assertSessionHasErrors('first_name');

        $this->actingAs($fresh)
            ->from('/profile/edit')
            ->put(route('profile.update.general'), [
                'nickname' => 'نام تازه',
            ])
            ->assertRedirect('/profile/edit')
            ->assertSessionHasNoErrors();

        $this->assertSame('سعید', $fresh->fresh()->first_name);
        $this->assertSame('نام تازه', $fresh->fresh()->nickname);
    }

    public function test_registration_and_profile_use_the_same_phone_country_catalog(): void
    {
        $catalog = config('phone-countries');

        $this->assertIsArray($catalog);
        $this->assertGreaterThan(50, count($catalog));

        $profileEdit = file_get_contents(app_path('Http/Controllers/LocationGovernance/ProfileEditController.php'));
        $step1 = file_get_contents(resource_path('views/partials/countries-list.blade.php'));
        $controller = file_get_contents(app_path('Http/Controllers/Auth/Register/Step1Controller.php'));

        $this->assertStringContainsString("config('phone-countries'", $profileEdit);
        $this->assertStringContainsString("config('phone-countries'", $step1);
        $this->assertStringNotContainsString("'in:+98,+1,+44,+49'", $controller);
    }

    public function test_same_local_phone_number_can_exist_under_different_country_codes(): void
    {
        User::factory()->create([
            'phone_country_code' => '+98',
            'phone' => '9123456789',
        ]);

        $other = User::factory()->create([
            'phone_country_code' => '+90',
            'phone' => '9123456789',
        ]);

        $this->assertSame('+90', $other->phone_country_code);
        $this->assertSame('9123456789', $other->phone);
    }

}
