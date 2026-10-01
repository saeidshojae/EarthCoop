<?php

namespace Tests\Feature\Auth;

use App\Models\EmailVerification;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

final class AuthSensitiveEmailSemanticsTest extends TestCase
{
    use RefreshDatabase;

    public function test_email_verification_generates_six_digit_code_valid_for_five_minutes(): void
    {
        Mail::fake();
        $now = Carbon::parse('2026-10-01 03:30:00', 'Asia/Tehran');
        Carbon::setTestNow($now);

        try {
            $email = 'verify@example.test';

            $this->post('/email/verify/send', ['email' => $email])
                ->assertRedirect(route('email.verify.form', ['email' => $email]));

            $verification = EmailVerification::query()->where('email', $email)->firstOrFail();
            $this->assertMatchesRegularExpression('/^\d{6}$/', (string) $verification->code);
            $this->assertSame($now->copy()->addMinutes(5)->timestamp, $verification->expires_at->timestamp);
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_email_verification_resend_preserves_still_valid_code(): void
    {
        Mail::fake();
        $now = Carbon::parse('2026-10-01 03:30:00', 'Asia/Tehran');
        Carbon::setTestNow($now);

        try {
            $user = User::factory()->create(['email' => 'resend@example.test', 'email_verified_at' => null]);
            EmailVerification::query()->create([
                'email' => $user->email,
                'code' => '123456',
                'expires_at' => $now->copy()->addMinutes(3),
            ]);

            $response = $this->from(route('email.verify.form', ['email' => $user->email]))
                ->post('/email/verify/resend', ['email' => $user->email]);

            $response->assertSessionHasErrors('resend');
            $this->assertDatabaseHas('email_verifications', [
                'email' => $user->email,
                'code' => '123456',
            ]);
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_password_reset_generates_six_digit_code_valid_for_five_minutes(): void
    {
        Mail::fake();
        $now = Carbon::parse('2026-10-01 03:30:00', 'Asia/Tehran');
        Carbon::setTestNow($now);

        try {
            $user = User::factory()->create(['email' => 'reset@example.test']);

            $this->post('/forgot-password', ['email' => $user->email])
                ->assertRedirect(route('password.reset.viewForm', ['email' => $user->email]));

            $verification = EmailVerification::query()->where('email', $user->email)->firstOrFail();
            $this->assertMatchesRegularExpression('/^\d{6}$/', (string) $verification->code);
            $this->assertSame($now->copy()->addMinutes(5)->timestamp, $verification->expires_at->timestamp);
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_successful_password_reset_consumes_code_and_updates_password(): void
    {
        $now = Carbon::parse('2026-10-01 03:30:00', 'Asia/Tehran');
        Carbon::setTestNow($now);

        try {
            $user = User::factory()->create(['email' => 'consume@example.test']);
            EmailVerification::query()->create([
                'email' => $user->email,
                'code' => '654321',
                'expires_at' => $now->copy()->addMinutes(5),
            ]);

            $this->post('/reset-password', [
                'email' => $user->email,
                'code' => '654321',
                'password' => 'new-password-123',
                'password_confirmation' => 'new-password-123',
            ])->assertRedirect(route('login'));

            $this->assertDatabaseMissing('email_verifications', [
                'email' => $user->email,
                'code' => '654321',
            ]);
            $this->assertTrue(\Illuminate\Support\Facades\Hash::check('new-password-123', (string) $user->fresh()->password));
        } finally {
            Carbon::setTestNow();
        }
    }
}
