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
}
