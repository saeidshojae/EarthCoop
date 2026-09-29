<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\InvitationCode;
use App\Models\Setting;
use App\Models\User;
use App\Models\UserExperience;
use App\Services\NajmHoda\Runtime\NajmHodaDomainEventPolicyLinkService;
use App\Services\NajmHoda\Runtime\RuntimeEventBus;
use App\Services\ProfileCompletionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\InvalidStateException;
use Throwable;

class GoogleController extends Controller
{
    private const MODE_SESSION_KEY = 'google_oauth_mode';

    public function redirectToGoogle(Request $request)
    {
        $mode = $request->boolean('login') ? 'login' : 'register';

        if (! $this->googleOAuthConfigured()) {
            return $this->oauthConfigurationError($mode);
        }

        if ($mode === 'register') {
            if ($request->session()->get('registration_terms_accepted') !== true) {
                return redirect()->route('welcome')->withErrors([
                    'terms' => 'برای ثبت‌نام با گوگل، ابتدا اساسنامه و شرایط استفاده را بپذیرید.',
                ]);
            }

            if ($this->invitationRequired()) {
                $invitationCode = $request->session()->get('registration_invitation_code');
                $validInvitation = InvitationCode::where('code', $invitationCode)
                    ->where('used', false)
                    ->where('expire_at', '>=', now())
                    ->exists();

                if (! $validInvitation) {
                    $request->session()->forget('registration_invitation_code');

                    return redirect()->route('welcome')->withErrors([
                        'invite_code' => 'برای ثبت‌نام با گوگل باید یک کد دعوت معتبر وارد کنید.',
                    ]);
                }
            } else {
                $request->session()->forget('registration_invitation_code');
            }
        }

        // Application intent belongs to our server-side session. OAuth `state`
        // remains exclusively owned by Socialite for CSRF protection.
        $request->session()->put(self::MODE_SESSION_KEY, $mode);

        return Socialite::driver('google')->redirect();
    }

    private function getIncompleteStep(User $user): ?string
    {
        if ($user->password == null || $user->national_id == null) {
            if (app(ProfileCompletionService::class)->hasRequiredResidence($user)) {
                return 'home';
            }

            return 'register.step1';
        }

        if (! UserExperience::where('user_id', $user->id)->exists()) {
            return 'register.step2';
        }

        if (! app(ProfileCompletionService::class)->hasRequiredResidence($user)) {
            return 'register.step3';
        }

        return null;
    }

    public function handleGoogleCallback(Request $request)
    {
        $this->emitRuntime('najm_hoda.input.auth.service.google_oauth.callback.requested', [
            'scope' => 'auth',
            'risk' => 'low',
        ]);

        $mode = $request->session()->pull(self::MODE_SESSION_KEY);

        if (! in_array($mode, ['login', 'register'], true)) {
            return redirect()->route('welcome')->withErrors([
                'google' => 'فرآیند ورود با گوگل معتبر نیست یا منقضی شده است. لطفاً دوباره تلاش کنید.',
            ]);
        }

        if (! $this->googleOAuthConfigured()) {
            return $this->oauthConfigurationError($mode);
        }

        try {
            // Stateful Socialite validates the provider's OAuth `state` against
            // the value stored in this browser session.
            $googleUser = Socialite::driver('google')->user();
            $email = trim((string) ($googleUser->getEmail() ?? ''));

            if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $this->emitRuntime('najm_hoda.input.auth.service.google_oauth.callback.rejected', [
                    'reason' => 'invalid_provider_email',
                    'scope' => 'auth',
                    'risk' => 'medium',
                ]);

                return redirect()->route('welcome')->withErrors([
                    'google' => 'حساب گوگل یک ایمیل معتبر در اختیار ارث‌کوپ قرار نداد. لطفاً از حساب دیگری استفاده کنید.',
                ]);
            }

            $user = User::whereEmail($email)->first();

            if ($user?->isSystemIdentity()) {
                abort(403, 'ورود تعاملی با هویت سیستمی مجاز نیست.');
            }

            // Existing members may always authenticate with their matching
            // Google email. Registration gates apply only to creating a member.
            if ($user) {
                Auth::login($user);

                $this->emitRuntime('najm_hoda.input.auth.service.google_oauth.callback.succeeded', [
                    'mode' => 'login',
                    'user_id' => (int) $user->id,
                    'scope' => 'auth',
                    'risk' => 'low',
                ]);

                if ($step = $this->getIncompleteStep($user)) {
                    if ($step == 'home') {
                        $msg = 'Registration is incomplete. Please complete your identity data.';
                    } else {
                        $msg = 'Registration is incomplete. Please continue.';
                    }

                    return redirect()->route($step)->with('success', $msg);
                }

                return redirect()->route('home');
            }

            if ($mode === 'login') {
                $this->emitRuntime('najm_hoda.input.auth.service.google_oauth.callback.rejected', [
                    'reason' => 'user_not_found_for_login',
                    'email' => $email,
                    'scope' => 'auth',
                    'risk' => 'medium',
                ]);

                return redirect()->route('welcome')->withErrors([
                    'google' => 'برای این ایمیل حسابی پیدا نشد. لطفاً ابتدا ثبت‌نام کنید.',
                ]);
            }

            // Registration preconditions are intentionally checked again at
            // callback time. The session or invitation can change while the
            // member is away at Google.
            if ($request->session()->get('registration_terms_accepted') !== true) {
                return redirect()->route('welcome')->withErrors([
                    'terms' => 'برای ثبت‌نام با گوگل، ابتدا اساسنامه و شرایط استفاده را بپذیرید.',
                ]);
            }

            $invitationRequired = $this->invitationRequired();
            $invitationCode = $invitationRequired
                ? $request->session()->get('registration_invitation_code')
                : null;

            if (! $invitationRequired) {
                $request->session()->forget('registration_invitation_code');
            }

            $user = DB::transaction(function () use ($googleUser, $email, $invitationRequired, $invitationCode) {
                $invitation = null;

                if ($invitationRequired) {
                    // Match normal registration: final validation and claim
                    // happen under a row lock in the same transaction as user
                    // creation, preventing concurrent double-consumption.
                    $invitation = InvitationCode::where('code', $invitationCode)
                        ->lockForUpdate()
                        ->first();

                    if (! $invitation
                        || $invitation->used
                        || ! $invitation->expire_at
                        || $invitation->expire_at->lt(now())) {
                        return null;
                    }
                }

                $newUser = User::create([
                    'email' => $email,
                    'name' => $googleUser->getName(),
                    'email_verified_at' => now(),
                    'fingerprint_id' => session('fingerprint_id'),
                    'terms_accepted_at' => now(),
                ]);

                if ($invitation) {
                    $invitation->forceFill([
                        'used' => true,
                        'used_by' => $newUser->id,
                        'used_at' => now(),
                    ])->save();
                }

                return $newUser;
            });

            if (! $user) {
                $request->session()->forget('registration_invitation_code');

                return redirect()->route('welcome')->withErrors([
                    'invite_code' => 'کد دعوت معتبر نیست، منقضی شده یا هم‌زمان توسط عضو دیگری استفاده شده است. لطفاً کد دعوت دیگری وارد کنید.',
                ]);
            }

            Auth::login($user);
            $this->clearRegistrationGateSession($request);

            $this->emitRuntime('najm_hoda.input.auth.service.google_oauth.callback.succeeded', [
                'mode' => 'register',
                'user_id' => (int) $user->id,
                'scope' => 'auth',
                'risk' => 'low',
            ]);

            return redirect()->route('register.step1')->with('success', 'Registration started successfully.');
        } catch (InvalidStateException $e) {
            $this->emitRuntime('najm_hoda.input.auth.service.google_oauth.callback.rejected', [
                'reason' => 'invalid_oauth_state',
                'scope' => 'auth',
                'risk' => 'medium',
            ]);

            return redirect()->route($mode === 'login' ? 'login' : 'welcome')->withErrors([
                'google' => 'نشست ورود با گوگل معتبر نیست یا منقضی شده است. لطفاً دوباره تلاش کنید.',
            ]);
        } catch (Throwable $e) {
            $this->emitRuntime('najm_hoda.input.auth.service.google_oauth.callback.failed', [
                'error' => $e->getMessage(),
                'scope' => 'auth',
                'risk' => 'high',
            ]);

            throw $e;
        }
    }

    private function invitationRequired(): bool
    {
        $setting = Setting::find(1);

        return $setting && (int) $setting->invation_status === 1;
    }

    private function googleOAuthConfigured(): bool
    {
        return filled(config('services.google.client_id'))
            && filled(config('services.google.client_secret'))
            && filled(config('services.google.redirect'));
    }

    private function oauthConfigurationError(string $mode)
    {
        return redirect()->route($mode === 'login' ? 'login' : 'welcome')->withErrors([
            'google' => 'ورود با گوگل در حال حاضر پیکربندی نشده است. لطفاً از روش ورود دیگری استفاده کنید یا بعداً دوباره تلاش کنید.',
        ]);
    }

    private function clearRegistrationGateSession(Request $request): void
    {
        $request->session()->forget([
            'registration_invitation_code',
            'registration_terms_accepted',
            'registration_terms_accepted_at',
        ]);
    }

    /**
     * @param array<string, mixed> $payload
     */
    protected function emitRuntime(string $event, array $payload): void
    {
        try {
            /** @var RuntimeEventBus $bus */
            $bus = app(RuntimeEventBus::class);
            $bus->emit($event, $payload);

            /** @var NajmHodaDomainEventPolicyLinkService $link */
            $link = app(NajmHodaDomainEventPolicyLinkService::class);
            $link->ingest($event, $payload);
        } catch (Throwable) {
            // no-op
        }
    }
}
