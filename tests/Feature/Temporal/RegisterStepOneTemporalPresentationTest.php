<?php

namespace Tests\Feature\Temporal;

use Tests\TestCase;

class RegisterStepOneTemporalPresentationTest extends TestCase
{
    public function test_register_step_one_uses_shared_picker_instead_of_legacy_birth_date_selects(): void
    {
        $controller = file_get_contents(base_path('app/Http/Controllers/Auth/Register/Step1Controller.php'));
        $view = file_get_contents(base_path('resources/views/auth/register_step1.blade.php'));

        $this->assertStringContainsString("return view('auth.register_step1');", $controller);
        $this->assertStringNotContainsString('$birthYearMax', $controller);
        $this->assertStringNotContainsString('$birthYearMin', $controller);

        $this->assertStringContainsString('<x-temporal.date-input', $view);
        $this->assertStringContainsString('name="birth_date"', $view);
        $this->assertStringNotContainsString('name="birth_date[]"', $view);
        $this->assertStringNotContainsString('@for ($i = $birthYearMax;', $view);
        $this->assertStringNotContainsString('Morilog\\Jalali', $view);
        $this->assertStringNotContainsString('Jalalian::', $view);
    }
}
