<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Address;
use App\Models\User;
use App\Models\UserExperience;
use App\Services\NajmHoda\Runtime\NajmHodaDomainEventPolicyLinkService;
use App\Services\NajmHoda\Runtime\RuntimeEventBus;
use Illuminate\Support\Facades\Auth;
use Laravel\Socialite\Facades\Socialite;
use Throwable;

class GoogleController extends Controller
{
    public function redirectToGoogle()
    {
        $login = request()->has('login') && request()->get('login') == '1';
        $state = $login ? 'login' : 'register';

        return Socialite::driver('google')
            ->with(['state' => $state])
            ->redirect();
    }

    private function getIncompleteStep(User $user): ?string
    {
        if ($user->password == null || $user->national_id == null) {
            if (Address::where('user_id', $user->id)->exists()) {
                return 'home';
            }

            return 'register.step1';
        }

        if (!UserExperience::where('user_id', $user->id)->exists()) {
            return 'register.step2';
        }

        if (!Address::where('user_id', $user->id)->exists()) {
            return 'register.step3';
        }

        return null;
    }

    public function handleGoogleCallback()
    {
        $this->emitRuntime('najm_hoda.input.auth.service.google_oauth.callback.requested', [
            'scope' => 'auth',
            'risk' => 'low',
        ]);

        try {
            $googleUser = Socialite::driver('google')->stateless()->user();
            $state = request()->get('state');
            $email = (string) ($googleUser->getEmail() ?? '');
            $user = User::whereEmail($email)->first();

            if ($user?->isSystemIdentity()) {
                abort(403, 'System identities cannot sign in interactively.');
            }

            if (!$user) {
                if ($state === 'login') {
                    $this->emitRuntime('najm_hoda.input.auth.service.google_oauth.callback.rejected', [
                        'reason' => 'user_not_found_for_login',
                        'email' => $email,
                        'scope' => 'auth',
                        'risk' => 'medium',
                    ]);

                    return redirect()->route('welcome')->with('success', 'No account found for this email. Please register first.');
                }

                $user = User::create([
                    'email' => $email,
                    'name' => $googleUser->getName(),
                    'email_verified_at' => now(),
                ]);

                Auth::login($user);

                $this->emitRuntime('najm_hoda.input.auth.service.google_oauth.callback.succeeded', [
                    'mode' => 'register',
                    'user_id' => (int) $user->id,
                    'scope' => 'auth',
                    'risk' => 'low',
                ]);

                return redirect()->route('register.step1')->with('success', 'Registration started successfully.');
            }

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
        } catch (Throwable $e) {
            $this->emitRuntime('najm_hoda.input.auth.service.google_oauth.callback.failed', [
                'error' => $e->getMessage(),
                'scope' => 'auth',
                'risk' => 'high',
            ]);

            throw $e;
        }
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
