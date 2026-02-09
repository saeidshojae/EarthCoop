<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class UpdateLastSeenOnLogout
{
    public function handle(Request $request, Closure $next)
    {
        if (Auth::check()) {
            DB::table('users')
                ->where('id', Auth::id())
                ->update(['last_seen' => now()]);
        }
        
        return $next($request);
    }
} 