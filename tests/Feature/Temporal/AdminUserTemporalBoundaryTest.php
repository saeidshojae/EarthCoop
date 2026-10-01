<?php

namespace Tests\Feature\Temporal;

use App\Http\Controllers\Admin\TemporalSafeUserController;
use App\Http\Controllers\Admin\UserController;
use App\Temporal\Context\TemporalContextResolver;
use App\Temporal\Contracts\TemporalService;
use Tests\TestCase;

class AdminUserTemporalBoundaryTest extends TestCase
{
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
}
