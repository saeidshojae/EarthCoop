<?php

namespace Tests\Feature\LocationGovernance;

use Tests\TestCase;

final class RegistrationStep3OriginalUxContractTest extends TestCase
{
    public function test_canonical_step_three_preserves_the_established_visual_language_and_canonical_picker(): void
    {
        $view = file_get_contents(resource_path('views/auth/register_step3_canonical.blade.php'));
        $selector = file_get_contents(resource_path('js/location-selector.js'));

        self::assertIsString($view);
        self::assertIsString($selector);

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

        // The restored presentation must remain wired to the canonical runtime.
        self::assertStringContainsString('data-location-selector-context="registration"', $view);
        self::assertStringContainsString('data-location-id', $view);
        self::assertStringContainsString('data-location-proposal-id', $view);
        self::assertStringContainsString('data-location-levels', $view);
        self::assertStringContainsString('data-location-path', $view);
        self::assertStringContainsString('data-location-geolocation-detect', $view);
        self::assertStringContainsString('data-location-submit', $view);

        // Proposal affordance is real and policy-driven: the shared selector creates it
        // only when the API marks a child type proposal_allowed=true. Step 3 styles that
        // actual runtime control in the established green visual language; no fake hidden
        // button is kept in the Blade merely to satisfy this contract.
        self::assertStringContainsString('data-location-proposal-toggle', $view);
        self::assertStringContainsString('مکان من در فهرست نیست', $selector);
        self::assertStringContainsString('proposal_allowed === true', $selector);
        self::assertStringNotContainsString('data-registration-proposal-visual-hint', $view);

        // Mobile remains first-class and the real proposal control has a 44px target.
        $compactView = preg_replace('/\s+/', '', $view);
        self::assertStringContainsString('@media(max-width:640px)', $compactView);
        self::assertStringContainsString('[data-location-proposal-toggle]{font-size:.8125rem!important;padding:.5rem1rem!important;min-height:44px}', $compactView);
    }
}
