<?php

namespace Tests\Feature\Temporal;

use Tests\TestCase;

class RegisterStepOneTemporalPresentationTest extends TestCase
{
    public function test_register_step_one_receives_birth_year_range_from_temporal_controller_boundary(): void
    {
        $controller = file_get_contents(base_path('app/Http/Controllers/Auth/Register/Step1Controller.php'));
        $view = file_get_contents(base_path('resources/views/auth/register_step1.blade.php'));

        $this->assertStringContainsString('$birthYearMax = $this->temporal->year(', $controller);
        $this->assertStringContainsString('$birthYearMin = $birthYearMax - 135;', $controller);
        $this->assertStringContainsString("return view('auth.register_step1', compact('birthYearMax', 'birthYearMin'));", $controller);

        $this->assertStringContainsString('@for ($i = $birthYearMax; $i >= $birthYearMin; $i--)', $view);
        $this->assertStringNotContainsString('Morilog\\Jalali', $view);
        $this->assertStringNotContainsString('Jalalian::', $view);
    }
}
