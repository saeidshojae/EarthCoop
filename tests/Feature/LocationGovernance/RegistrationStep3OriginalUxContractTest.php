<?php

namespace Tests\Feature\LocationGovernance;

use Tests\TestCase;

final class RegistrationStep3OriginalUxContractTest extends TestCase
{
    public function test_canonical_step_three_preserves_the_established_visual_language_and_canonical_picker(): void
    {
        $view = file_get_contents(resource_path('views/auth/register_step3_canonical.blade.php'));

        self::assertIsString($view);

        // Established Step 3 visual language from the mature pre-cutover screen.
        self::assertStringContainsString("asset('Css/fonts-local.css')", $view);
        self::assertStringContainsString("'Vazirmatn', 'Poppins', sans-serif", $view);
        self::assertStringContainsString('--color-earth-green: #10b981', $view);
        self::assertStringContainsString('--color-ocean-blue: #3b82f6', $view);
        self::assertStringContainsString('--color-digital-gold: #f59e0b', $view);
        self::assertStringContainsString('linear-gradient(90deg, var(--color-earth-green), var(--color-ocean-blue), var(--color-digital-gold))', $view);
        self::assertStringContainsString('EarthCoop', $view);
        self::assertStringContainsString('هویتی', $view);
        self::assertStringContainsString('صنفی', $view);
        self::assertStringContainsString('مکانی', $view);
        self::assertStringContainsString('location-path', $view);
        self::assertStringContainsString('linear-gradient(135deg, #667eea 0%, #764ba2 100%)', $view);
        self::assertStringContainsString('create-location-btn', $view);

        // The restored presentation must remain wired to the canonical runtime.
        self::assertStringContainsString('data-location-selector-context="registration"', $view);
        self::assertStringContainsString('data-location-id', $view);
        self::assertStringContainsString('data-location-proposal-id', $view);
        self::assertStringContainsString('data-location-levels', $view);
        self::assertStringContainsString('data-location-path', $view);
        self::assertStringContainsString('data-location-geolocation-detect', $view);
        self::assertStringContainsString('data-location-submit', $view);

        // Mobile remains first-class and proposal UX keeps a touch-friendly affordance.
        self::assertStringContainsString('@media(max-width:640px)', preg_replace('/\s+/', '', $view));
        self::assertStringContainsString('min-height:44px', preg_replace('/\s+/', '', $view));
        self::assertStringContainsString('مکان من در فهرست نیست', $view);
    }
}
