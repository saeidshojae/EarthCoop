<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\EmailVerification;
use App\Models\User;
use App\Services\Communication\CommunicationDispatcher;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class EmailVerificationController extends Controller
{
    public function __construct(
        private readonly CommunicationDispatcher $communications,
    ) {
    }

    public function sendVerificationCode(Request $request)
    {
        $email = $request->email;
        $code = sprintf('%06d', random_int(0, 999999));

        EmailVerification::updateOrCreate(
            ['email' => $email],
            [
                'code' => $code,
                'expires_at' => Carbon::now()->addMinutes(5),
            ]
        );

        $this->communications->dispatchExternal(
            'auth.email_verification',
            ['type' => 'email_verification', 'id' => (string) $email],
            [[
                'email' => (string) $email,
                'locale' => 'fa',
            ]],
            [
                'code' => (string) $code,
            ],
            [
                'locale' => 'fa',
                'deduplication_key' => 'auth.email_verification:'.Str::uuid(),
            ],
        );

        return redirect()->route('email.verify.form', ['email' => $email])
            ->with('success', 'کد تأیید به ایمیل شما ارسال شد.');
    }

    public function showVerificationForm(Request $request)
    {
        return view('auth.verify-email', ['email' => $request->email]);
    }

    public function verify(Request $request)
    {
        $email = $request->email;
        $code = implode('', $request->code);

        $verification = EmailVerification::where('email', $email)
            ->where('code', $code)
            ->where('expires_at', '>', Carbon::now())
            ->first();

        if (! $verification) {
            return back()->withErrors(['verification_code' => 'کد وارد شده نامعتبر است.']);
        }

        $user = User::where('email', $email)->first();
        abort_if($user?->isSystemIdentity(), 403, 'System identities cannot sign in interactively.');
        $user->email_verified_at = Carbon::now();
        $user->save();

        try {
            app(\App\Services\ReputationService::class)->applyAction(
                $user,
                'email_verified',
                ['email' => $email],
                null,
                'auth',
                'email_verified:user:' . $user->id
            );
        } catch (\Throwable $e) {
            \Log::error('Reputation applyAction failed (email_verified): ' . $e->getMessage());
        }

        $verification->delete();

        auth()->login($user);

        return redirect()->route('register.step1')
            ->with('congratulations', true);
    }

    public function resend(Request $request)
    {
        $request->validate([
            'email' => 'required|email|exists:users,email',
        ]);

        $email = $request->email;

        $existing = EmailVerification::where('email', $email)
            ->where('expires_at', '>', now())
            ->first();

        if ($existing) {
            $remainingSeconds = now()->diffInSeconds($existing->expires_at);

            return back()->withErrors([
                'resend' => 'کد قبلی هنوز معتبر است. لطفا پس از '.ceil($remainingSeconds / 60).' دقیقه دوباره تلاش کنید.',
            ]);
        }

        return $this->sendVerificationCode($request);
    }
}
