<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureEmailIsVerified
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (Auth::check() && !Auth::user()->email_verified_at) {
            return redirect()->route('email.verify.form', ['email' => Auth::user()->email])
                           ->with('error', 'لطفاً ابتدا ایمیل خود را تأیید کنید.');
        }

        return $next($request);
    }
}  