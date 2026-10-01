<?php

namespace Tests\Feature\Auth;

use App\Temporal\Context\TemporalContextResolver;
use App\Temporal\Contracts\TemporalService;
use Tests\TestCase;

class RegistrationTemporalContractTest extends TestCase
{
    public function test_persian_and_english_birth_date_parts_resolve_to_same_canonical_date(): void
    {
        $temporal = app(TemporalService::class);
        $contexts = app(TemporalContextResolver::class);

        $persian = $temporal->parseDateParts(9, 7, 1405, $contexts->forLocale('fa'));
        $english = $temporal->parseDateParts(1, 10, 2026, $contexts->forLocale('en'));

        $this->assertSame('2026-10-01', $persian->toCanonical());
        $this->assertSame($persian->toCanonical(), $english->toCanonical());
    }

    public function test_default_registration_context_follows_active_application_locale(): void
    {
        $contexts = app(TemporalContextResolver::class);

        app()->setLocale('fa');
        $this->assertSame('jalali', $contexts->defaultContext()->calendar());

        app()->setLocale('en');
        $this->assertSame('gregorian', $contexts->defaultContext()->calendar());
    }

    public function test_step_one_controller_has_no_direct_jalali_library_dependency(): void
    {
        $source = file_get_contents(app_path('Http/Controllers/Auth/Register/Step1Controller.php'));

        $this->assertStringNotContainsString('Morilog\\Jalali', $source);
        $this->assertStringNotContainsString('Jalalian', $source);
    }
}
