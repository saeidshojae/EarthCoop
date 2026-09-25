<?php

namespace Tests\Feature\LocationGovernance;

use Tests\TestCase;

class RegistrationStep3CanonicalUxRegressionTest extends TestCase
{
    public function test_canonical_registration_preserves_the_established_step_three_visual_contract(): void
    {
        $view = file_get_contents(resource_path('views/auth/register_step3_canonical.blade.php'));
        $app = file_get_contents(resource_path('js/app.js'));

        $this->assertIsString($view);
        $this->assertIsString($app);
        $this->assertStringContainsString('form-card-gradient', $view);
        $this->assertStringContainsString('مرحله ۳: اطلاعات مکانی', $view);
        $this->assertStringContainsString('location-path', $view);
        $this->assertStringContainsString('data-location-path', $view);
        $this->assertStringContainsString('مسیر انتخاب نشده', $view);
        $this->assertStringContainsString('data-location-selector', $view);
        $this->assertStringContainsString('data-location-geolocation-detect', $view);
        $this->assertMatchesRegularExpression('/import\s+[\'\"]\.\/registration-location-ux\.js[\'\"]\s*;/', $app);
    }

    public function test_registration_enhancement_exposes_a_live_canonical_path_contract(): void
    {
        $source = file_get_contents(resource_path('js/registration-location-ux.js'));

        $this->assertIsString($source);
        $this->assertStringContainsString('[data-location-path]', $source);
        $this->assertStringContainsString('data-location-path-item', $source);
        $this->assertStringContainsString('location-proposal', $source);
    }

    public function test_successful_geolocation_can_hydrate_the_canonical_registration_selection_instead_of_only_showing_a_message(): void
    {
        $source = file_get_contents(resource_path('js/location-geolocation.js'));

        $this->assertIsString($source);
        $this->assertStringContainsString('suggested_location_id', $source);
        $this->assertStringContainsString('location:hydrate', $source);
    }
    public function test_registration_explains_governance_base_stop_and_defers_micro_location_details(): void
    {
        $view = file_get_contents(resource_path('views/auth/register_step3_canonical.blade.php'));

        $this->assertIsString($view);
        $this->assertStringContainsString('تا سطح محله', $view);
        $this->assertStringContainsString('ثبت‌نام در همین سطح پایه پایان می‌یابد', $view);
        $this->assertStringContainsString('نیازی به وارد کردن خیابان، کوچه، مجتمع یا ساختمان نیست', $view);
        $this->assertStringContainsString('مکان و حکمرانی من', $view);
    }

}
