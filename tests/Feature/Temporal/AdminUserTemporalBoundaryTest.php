<?php

namespace Tests\Feature\Temporal;

use App\Http\Controllers\Admin\TemporalSafeUserController;
use App\Http\Controllers\Admin\UserController;
use App\Models\User;
use App\Temporal\Context\TemporalContextResolver;
use App\Temporal\Contracts\TemporalService;
use App\Temporal\ValueObjects\LocalDate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

class AdminUserTemporalBoundaryTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_user_controller_binding_resolves_temporal_safe_boundary(): void
    {
        $this->assertInstanceOf(TemporalSafeUserController::class, app(UserController::class));
    }

    public function test_admin_user_temporal_adapter_does_not_depend_on_legacy_calendar_api(): void
    {
        $source = file_get_contents(app_path('Http/Controllers/Admin/TemporalSafeUserController.php'));

        $this->assertStringContainsString(TemporalService::class, $source);
        $this->assertStringContainsString(TemporalContextResolver::class, $source);
        $this->assertStringNotContainsString('Morilog\\Jalali', $source);
        $this->assertStringNotContainsString('Jalalian::', $source);
        $this->assertStringNotContainsString('verta(', $source);
    }

    public function test_admin_user_birth_date_contract_maps_equivalent_localized_dates_to_same_canonical_date(): void
    {
        $temporal = app(TemporalService::class);
        $contexts = app(TemporalContextResolver::class);

        $fa = $temporal->parseDateParts(9, 7, 1405, $contexts->forLocale('fa', 'UTC'));
        $en = $temporal->parseDateParts(1, 10, 2026, $contexts->forLocale('en', 'UTC'));

        $this->assertSame('2026-10-01', $fa->toCanonical());
        $this->assertSame($fa->toCanonical(), $en->toCanonical());
    }

    public function test_admin_user_store_accepts_gregorian_localized_birth_date_string(): void
    {
        app()->setLocale('en');

        $email = 'temporal-admin-en-'.uniqid().'@example.test';
        $request = Request::create('/admin/users', 'POST', [
            'email' => $email,
            'first_name' => 'علی',
            'last_name' => 'رضایی',
            'birth_date' => '1990-01-01',
            'gender' => 'male',
            'national_id' => '1234567806',
            'phone' => '09123456780',
            'password' => 'secret123',
            'password_confirmation' => 'secret123',
        ]);

        app(UserController::class)->store($request);

        $user = User::query()->where('email', $email)->firstOrFail();
        $this->assertSame('1990-01-01', $user->birth_date->format('Y-m-d'));
    }

    public function test_admin_user_store_accepts_jalali_localized_birth_date_string(): void
    {
        app()->setLocale('fa');
        $temporal = app(TemporalService::class);
        $contexts = app(TemporalContextResolver::class);
        $localized = $temporal->date(
            LocalDate::fromCanonical('1990-01-01'),
            $contexts->forLocale('fa', 'UTC'),
            'short',
        );

        $email = 'temporal-admin-fa-'.uniqid().'@example.test';
        $request = Request::create('/admin/users', 'POST', [
            'email' => $email,
            'first_name' => 'علی',
            'last_name' => 'رضایی',
            'birth_date' => $localized,
            'gender' => 'male',
            'national_id' => '1234567806',
            'phone' => '09123456781',
            'password' => 'secret123',
            'password_confirmation' => 'secret123',
        ]);

        app(UserController::class)->store($request);

        $user = User::query()->where('email', $email)->firstOrFail();
        $this->assertSame('1990-01-01', $user->birth_date->format('Y-m-d'));
    }

    public function test_admin_user_update_accepts_gregorian_localized_birth_date_string(): void
    {
        app()->setLocale('en');

        $user = User::factory()->create([
            'first_name' => 'علی',
            'last_name' => 'رضایی',
            'birth_date' => '1990-01-01',
            'gender' => 'male',
            'national_id' => '1234567891',
            'phone' => '09123456789',
            'status' => 'active',
        ]);

        $request = Request::create('/admin/users/'.$user->id, 'PUT', [
            'email' => $user->email,
            'first_name' => 'علی',
            'last_name' => 'رضایی',
            'birth_date' => '1985-05-15',
            'gender' => 'male',
            'national_id' => '1234567806',
            'phone' => '09123456780',
            'password' => null,
        ]);

        app(UserController::class)->update($request, $user);

        $this->assertSame('1985-05-15', $user->fresh()->birth_date->format('Y-m-d'));
    }

    public function test_admin_user_create_and_edit_forms_use_temporal_date_input_without_direct_jalali_api(): void
    {
        foreach (['admin/user/create.blade.php', 'admin/user/edit.blade.php'] as $path) {
            $source = file_get_contents(resource_path('views/'.$path));

            $this->assertStringContainsString('<x-temporal.date-input', $source, $path);
            $this->assertStringNotContainsString('Morilog\\Jalali', $source, $path);
            $this->assertStringNotContainsString('Jalalian::', $source, $path);
        }
    }

    public function test_admin_user_read_filters_use_local_day_boundaries_not_where_date(): void
    {
        $source = file_get_contents(app_path('Http/Controllers/Admin/TemporalSafeUserController.php'));

        $this->assertStringContainsString('public function index(', $source);
        $this->assertStringContainsString('public function transactions(', $source);
        $this->assertStringContainsString('startOfDay(', $source);
        $this->assertStringContainsString('endOfDay(', $source);
        $this->assertStringNotContainsString('whereDate(', $source);
    }
}
