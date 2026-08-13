<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Middleware\RedirectIfAuthenticated;
use App\Models\Address;
use App\Models\EmailVerification;
use App\Models\User;
use App\Models\UserExperience;
use App\Services\NajmHoda\Runtime\NajmHodaDomainEventPolicyLinkService;
use App\Services\NajmHoda\Runtime\RuntimeEventBus;
use Carbon\Carbon;
use Illuminate\Foundation\Auth\AuthenticatesUsers;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Throwable;

class LoginController extends Controller
{
    use AuthenticatesUsers {
        sendFailedLoginResponse as traitSendFailedLoginResponse;
    }

    /**
     * @var string
     */
    protected $redirectTo = '/home';

    public function __construct()
    {
        $this->middleware(RedirectIfAuthenticated::class)->except('logout');
    }

    protected function redirectTo()
    {
        if (auth()->user()->national_id == null) {
            return route('register.step1');
        }

        if (!UserExperience::where('user_id', auth()->user()->id)->exists()) {
            return route('register.step2');
        }

        if (!Address::where('user_id', auth()->user()->id)->exists()) {
            return route('register.step3');
        }

        return '/home';
    }

    protected function authenticated(Request $request, $user)
    {
        if ($user->isSystemIdentity()) {
            $this->guard()->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            abort(403, 'System identities cannot sign in interactively.');
        }

        $user->update([
            'last_login_ip' => $request->ip(),
            'last_login_at' => now(),
        ]);

        $this->emitRuntime('najm_hoda.input.auth.service.login.succeeded', [
            'user_id' => $user?->id !== null ? (int) $user->id : null,
            'scope' => 'auth',
            'risk' => 'low',
        ]);
    }

    protected function sendFailedLoginResponse(Request $request)
    {
        $this->emitRuntime('najm_hoda.input.auth.service.login.failed', [
            'email' => (string) $request->input('email', ''),
            'scope' => 'auth',
            'risk' => 'medium',
        ]);

        return $this->traitSendFailedLoginResponse($request);
    }

    public function logout(Request $request)
    {
        $this->emitRuntime('najm_hoda.input.auth.service.logout.requested', [
            'user_id' => auth()->id() !== null ? (int) auth()->id() : null,
            'scope' => 'auth',
            'risk' => 'low',
        ]);

        $this->guard()->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        cache()->flush();

        $this->emitRuntime('najm_hoda.input.auth.service.logout.succeeded', [
            'scope' => 'auth',
            'risk' => 'low',
        ]);

        return redirect('/')->with('clearLocalStorage', true);
    }

    public function forgotView()
    {
        return view('auth.reset-password.forgot');
    }

    public function resetView()
    {
        if (isset($_GET['email'])) {
            $email = $_GET['email'];
            return view('auth.reset-password.reset', compact('email'));
        }

        abort(404);
    }

    public function forgot(Request $request)
    {
        $this->emitRuntime('najm_hoda.input.auth.service.password_reset.requested', [
            'email' => (string) $request->input('email', ''),
            'scope' => 'auth',
            'risk' => 'low',
        ]);

        $request->validate(['email' => 'required|email|exists:users,email']);
        $email = $request->email;

        try {
            $code = sprintf('%06d', random_int(0, 999999));

            EmailVerification::updateOrCreate(
                ['email' => $email],
                [
                    'code' => $code,
                    'expires_at' => Carbon::now()->addMinutes(5),
                ]
            );

            Mail::send('emails.change-pass', ['code' => $code], function ($message) use ($email) {
                $message->to($email)->subject('Password change verification code');
            });

            $this->emitRuntime('najm_hoda.input.auth.service.password_reset.succeeded', [
                'email' => (string) $email,
                'scope' => 'auth',
                'risk' => 'low',
            ]);

            return redirect()->route('password.reset.viewForm', ['email' => $email])
                ->with('success', 'Reset code sent to your email.');
        } catch (Throwable $e) {
            $this->emitRuntime('najm_hoda.input.auth.service.password_reset.failed', [
                'email' => (string) $email,
                'error' => $e->getMessage(),
                'scope' => 'auth',
                'risk' => 'high',
            ]);

            throw $e;
        }
    }

    public function reset(Request $request)
    {
        $request->validate([
            'email' => 'required|email|exists:users,email',
            'code' => 'required',
            'password' => 'required|min:8|confirmed',
        ]);

        $code = $request->code;
        $email = $request->email;

        $verification = EmailVerification::where('email', $email)
            ->where('code', $code)
            ->where('expires_at', '>', Carbon::now())
            ->first();

        if (!$verification) {
            $this->emitRuntime('najm_hoda.input.auth.service.password_change.rejected', [
                'email' => (string) $email,
                'reason' => 'verification_code_invalid_or_expired',
                'scope' => 'auth',
                'risk' => 'medium',
            ]);

            return back()->with('error', 'Entered code is invalid.');
        }

        $user = User::where('email', $request->email)->first();
        $user->update([
            'password' => Hash::make($request->password),
        ]);

        $verification->delete();

        $this->emitRuntime('najm_hoda.input.auth.service.password_change.succeeded', [
            'email' => (string) $email,
            'user_id' => $user?->id !== null ? (int) $user->id : null,
            'scope' => 'auth',
            'risk' => 'low',
        ]);

        return redirect()->route('login')->with('success', 'Password changed successfully.');
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
