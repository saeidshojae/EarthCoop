<?php

namespace Tests\Feature\Auth;

use App\Http\Controllers\Auth\EmailVerificationController;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class RegistrationVerificationDiHotfixTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_resolves_email_verification_controller_from_container_and_redirects_to_verify_form(): void
    {
        $verification = Mockery::mock(EmailVerificationController::class);
        $verification->shouldReceive('sendVerificationCode')
            ->once()
            ->andReturn(redirect()->route('email.verify.form', ['email' => 'member@example.com']));

        $this->app->instance(EmailVerificationController::class, $verification);

        $response = $this
            ->withSession([
                'registration_terms_accepted' => true,
                'registration_terms_accepted_at' => now()->toIso8601String(),
                'fingerprint_id' => 'test-fingerprint',
            ])
            ->post(route('register.process'), [
                'email' => 'member@example.com',
                'password' => 'secret123',
                'password_confirmation' => 'secret123',
            ]);

        $response->assertRedirect(route('email.verify.form', ['email' => 'member@example.com']));

        $this->assertDatabaseHas('users', [
            'email' => 'member@example.com',
        ]);

        $user = User::query()->where('email', 'member@example.com')->firstOrFail();
        $this->assertAuthenticatedAs($user);
    }
}
