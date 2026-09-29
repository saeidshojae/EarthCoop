<?php

namespace Tests\Feature\Auth;

use App\Models\InvitationCode;
use App\Models\Setting;
use App\Models\User;
use Database\Seeders\SettingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;
use Mockery;
use Tests\TestCase;

class GoogleOAuthRegistrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app(SettingSeeder::class)->run();
        config()->set('services.google.client_id', 'test-client-id');
        config()->set('services.google.client_secret', 'test-client-secret');
        config()->set('services.google.redirect', 'https://earthcoop.test/auth/google/callback');
    }

    public function test_google_registration_cannot_start_before_terms_are_accepted(): void
    {
        Socialite::shouldReceive('driver')->never();

        $response = $this->get('/auth/google');

        $response->assertRedirect(route('welcome'));
        $response->assertSessionHasErrors('terms');
        $this->assertGuest();
    }

    public function test_google_registration_cannot_start_without_a_valid_invitation_when_invitation_is_required(): void
    {
        Socialite::shouldReceive('driver')->never();

        $response = $this
            ->withSession(['registration_terms_accepted' => true])
            ->get('/auth/google');

        $response->assertRedirect(route('welcome'));
        $response->assertSessionHasErrors('invite_code');
        $this->assertGuest();
    }

    public function test_google_redirect_uses_session_intent_and_does_not_override_oauth_state(): void
    {
        Setting::query()->findOrFail(1)->update(['invation_status' => false]);

        $provider = Mockery::mock();
        $provider->shouldReceive('with')->never();
        $provider->shouldReceive('redirect')
            ->once()
            ->andReturn(redirect('https://accounts.google.test/o/oauth2/auth'));

        Socialite::shouldReceive('driver')
            ->once()
            ->with('google')
            ->andReturn($provider);

        $response = $this
            ->withSession(['registration_terms_accepted' => true])
            ->get('/auth/google');

        $response->assertRedirect('https://accounts.google.test/o/oauth2/auth');
        $response->assertSessionHas('google_oauth_mode', 'register');
    }

    public function test_existing_user_can_sign_in_with_google_without_stateless_callback(): void
    {
        $existing = User::factory()->create([
            'email' => 'member@example.com',
            'is_system' => false,
        ]);

        $this->mockGoogleCallbackUser('member@example.com');

        $response = $this
            ->withSession(['google_oauth_mode' => 'login'])
            ->get('/auth/google/callback?state=attacker-controlled');

        $this->assertAuthenticatedAs($existing);
        $this->assertSame(1, User::query()->where('email', 'member@example.com')->count());
        $response->assertRedirect(route('register.step1'));
    }

    public function test_callback_mode_comes_from_session_not_mutable_state_query(): void
    {
        $this->mockGoogleCallbackUser('unknown@example.com');

        $response = $this
            ->withSession(['google_oauth_mode' => 'login'])
            ->get('/auth/google/callback?state=register');

        $response->assertRedirect(route('welcome'));
        $this->assertDatabaseMissing('users', ['email' => 'unknown@example.com']);
        $this->assertGuest();
    }

    public function test_new_google_registration_atomically_creates_user_and_consumes_invitation(): void
    {
        $inviter = User::factory()->create();
        $invitation = InvitationCode::create([
            'code' => 'GOOGLE-INVITE-1',
            'user_id' => $inviter->id,
            'used' => false,
            'expire_at' => now()->addHour(),
        ]);

        $this->mockGoogleCallbackUser('new-member@example.com');

        $response = $this
            ->withSession([
                'google_oauth_mode' => 'register',
                'registration_terms_accepted' => true,
                'registration_terms_accepted_at' => now()->subMinute()->toIso8601String(),
                'registration_invitation_code' => $invitation->code,
                'fingerprint_id' => 'fp-google-test',
            ])
            ->get('/auth/google/callback');

        // Keep response assertions ahead of database lookups so a callback gate
        // regression reports its real reason instead of surfacing as a secondary
        // ModelNotFoundException.
        $response->assertSessionHasNoErrors();
        $response->assertRedirect(route('register.step1'));

        $newUser = User::query()->where('email', 'new-member@example.com')->firstOrFail();
        $invitation->refresh();

        $this->assertAuthenticatedAs($newUser);
        $this->assertNotNull($newUser->email_verified_at);
        $this->assertNotNull($newUser->terms_accepted_at);
        $this->assertSame('fp-google-test', $newUser->fingerprint_id);
        $this->assertTrue($invitation->used);
        $this->assertSame($newUser->id, (int) $invitation->used_by);
        $this->assertNotNull($invitation->used_at);
        $response->assertSessionMissing('registration_invitation_code');
        $response->assertSessionMissing('registration_terms_accepted');
    }

    public function test_google_callback_does_not_create_user_when_terms_acceptance_is_missing(): void
    {
        Setting::query()->findOrFail(1)->update(['invation_status' => false]);
        $this->mockGoogleCallbackUser('no-terms@example.com');

        $response = $this
            ->withSession(['google_oauth_mode' => 'register'])
            ->get('/auth/google/callback');

        $response->assertRedirect(route('welcome'));
        $response->assertSessionHasErrors('terms');
        $this->assertDatabaseMissing('users', ['email' => 'no-terms@example.com']);
        $this->assertGuest();
    }

    public function test_google_callback_rechecks_invitation_before_creating_user(): void
    {
        $inviter = User::factory()->create();
        $invitation = InvitationCode::create([
            'code' => 'GOOGLE-EXPIRED',
            'user_id' => $inviter->id,
            'used' => false,
            'expire_at' => now()->subMinute(),
        ]);

        $this->mockGoogleCallbackUser('expired-invite@example.com');

        $response = $this
            ->withSession([
                'google_oauth_mode' => 'register',
                'registration_terms_accepted' => true,
                'registration_invitation_code' => $invitation->code,
            ])
            ->get('/auth/google/callback');

        $response->assertRedirect(route('welcome'));
        $response->assertSessionHasErrors('invite_code');
        $this->assertDatabaseMissing('users', ['email' => 'expired-invite@example.com']);
        $this->assertFalse($invitation->fresh()->used);
        $this->assertGuest();
    }

    public function test_google_callback_rejects_missing_or_malformed_email(): void
    {
        Setting::query()->findOrFail(1)->update(['invation_status' => false]);
        $this->mockGoogleCallbackUser('not-an-email');

        $response = $this
            ->withSession([
                'google_oauth_mode' => 'register',
                'registration_terms_accepted' => true,
            ])
            ->get('/auth/google/callback');

        $response->assertRedirect(route('welcome'));
        $response->assertSessionHasErrors('google');
        $this->assertDatabaseMissing('users', ['email' => 'not-an-email']);
        $this->assertGuest();
    }

    public function test_system_identity_is_rejected_from_google_login(): void
    {
        User::factory()->create([
            'email' => 'system@example.com',
            'is_system' => true,
        ]);
        $this->mockGoogleCallbackUser('system@example.com');

        $response = $this
            ->withSession(['google_oauth_mode' => 'login'])
            ->get('/auth/google/callback');

        $response->assertForbidden();
        $this->assertGuest();
    }

    public function test_incomplete_google_oauth_configuration_returns_persian_site_error_without_contacting_provider(): void
    {
        config()->set('services.google.client_id', null);
        Socialite::shouldReceive('driver')->never();

        $response = $this->get('/auth/google?login=1');

        $response->assertRedirect(route('login'));
        $response->assertSessionHasErrors([
            'google' => 'ورود با گوگل در حال حاضر پیکربندی نشده است. لطفاً از روش ورود دیگری استفاده کنید یا بعداً دوباره تلاش کنید.',
        ]);
    }

    private function mockGoogleCallbackUser(string $email): void
    {
        $googleUser = new SocialiteUser();
        $googleUser->map([
            'id' => 'google-user-1',
            'name' => 'Google Member',
            'email' => $email,
        ]);

        $provider = Mockery::mock();
        $provider->shouldReceive('stateless')->never();
        $provider->shouldReceive('user')->once()->andReturn($googleUser);

        Socialite::shouldReceive('driver')
            ->once()
            ->with('google')
            ->andReturn($provider);
    }
}
