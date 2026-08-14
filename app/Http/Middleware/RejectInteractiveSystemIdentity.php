<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class RejectInteractiveSystemIdentity
{
    public function handle(Request $request, Closure $next)
    {
        $user = Auth::user();

        if ($user?->isSystemIdentity()) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            abort(403, 'System identities cannot sign in interactively.');
        }

        return $next($request);
    }
}
