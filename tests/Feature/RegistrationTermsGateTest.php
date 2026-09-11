<?php

namespace Tests\Feature;

use Tests\TestCase;

class RegistrationTermsGateTest extends TestCase
{
    public function test_register_form_cannot_be_opened_without_terms_acceptance(): void
    {
        $response = $this->get(route('register.form'));

        $response->assertRedirect(route('welcome'));
        $response->assertSessionHasErrors('terms');
    }

    public function test_registration_cannot_be_submitted_without_terms_acceptance(): void
    {
        $response = $this->post(route('register.process'), [
            'email' => 'new-member@example.com',
            'password' => 'secret123',
            'password_confirmation' => 'secret123',
        ]);

        $response->assertRedirect(route('welcome'));
        $response->assertSessionHasErrors('terms');
    }

    public function test_welcome_country_statistics_follow_the_canonical_residence_rollout_flag(): void
    {
        $controller = file_get_contents(app_path('Http/Controllers/Auth/Register/StartController.php'));

        $this->assertStringContainsString("config('location-governance.registration_enabled')", $controller);
        $this->assertStringContainsString('user_location_relationships', $controller);
        $this->assertStringContainsString('locations.country_code', $controller);
        $this->assertStringContainsString("relationship_type', 'primary_residence'", $controller);
        $this->assertStringContainsString("whereNull('ended_at')", $controller);
        $this->assertStringContainsString("Schema::hasTable('addresses')", $controller);
    }
}
