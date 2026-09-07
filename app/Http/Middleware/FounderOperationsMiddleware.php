<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class FounderOperationsMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            return redirect('/home')->with('error', 'لطفا ابتدا وارد شوید');
        }

        if ((bool) $user->is_admin || $user->hasRole('super-admin')) {
            return $next($request);
        }

        if ($request->expectsJson()) {
            abort(403, 'دسترسی به مرکز مدیریت کل برای این حساب مجاز نیست.');
        }

        return redirect('/home')->with('error', 'دسترسی به مرکز مدیریت کل برای این حساب مجاز نیست.');
    }
}
