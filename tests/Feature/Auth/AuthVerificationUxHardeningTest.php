<?php

namespace Tests\Feature\Auth;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthVerificationUxHardeningTest extends TestCase
{
    use RefreshDatabase;

    public function test_verification_view_has_integer_countdown_and_complete_code_auto_submit_contract(): void
    {
        $source = file_get_contents(resource_path('views/auth/verify-email.blade.php'));

        $this->assertIsString($source);
        $this->assertStringNotContainsString('vendor/jquery/jquery.min.js', $source);
        $this->assertStringContainsString('(int) ceil(now()->diffInSeconds($verification->expires_at, false))', $source);
        $this->assertStringContainsString('id="verification-form"', $source);
        $this->assertStringContainsString('id="verify-button"', $source);
        $this->assertStringContainsString('disabled aria-disabled="true"', $source);
        $this->assertStringContainsString('const codeIsComplete', $source);
        $this->assertStringContainsString('window.setTimeout(submitVerification, 120)', $source);
        $this->assertStringContainsString("event.key === 'Enter'", $source);
        $this->assertStringContainsString('form.requestSubmit()', $source);
        $this->assertStringNotContainsString("addEventListener('keypress'", $source);
    }

    public function test_guest_auth_pages_do_not_render_najm_hoda_widget(): void
    {
        $this->get(route('login'))
            ->assertOk()
            ->assertDontSee('id="najm-hoda-widget"', false);

        $this->withSession(['registration_terms_accepted' => true])
            ->get(route('register.form'))
            ->assertOk()
            ->assertDontSee('id="najm-hoda-widget"', false);
    }

    public function test_auth_pages_do_not_depend_on_remote_iconfinder_google_icon(): void
    {
        foreach (['login.blade.php', 'register.blade.php'] as $file) {
            $source = file_get_contents(resource_path('views/auth/'.$file));

            $this->assertIsString($source);
            $this->assertStringNotContainsString('cdn1.iconfinder.com', $source);
            $this->assertStringContainsString('fill="#4285F4"', $source);
            $this->assertStringContainsString('fill="#34A853"', $source);
            $this->assertStringContainsString('fill="#FBBC05"', $source);
            $this->assertStringContainsString('fill="#EA4335"', $source);
        }
    }
}
