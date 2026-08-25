<?php
namespace App\Http\Middleware;

use Closure;
use Illuminate\Support\Facades\Auth;

class AdminMiddleware
{
    public function handle($request, Closure $next)
    {
        if (! Auth::check()) {
            return redirect('/home')->with('error', 'لطفا ابتدا وارد شوید');
        }

        $user = Auth::user();

        // Generic admin surfaces remain accessible to explicitly assigned
        // application roles. Sensitive Founder Operations is protected by its
        // own stricter middleware and must never rely on this generic gate.
        if ($user->is_admin || $user->hasRole('super-admin') || $user->roles()->exists()) {
            return $next($request);
        }

        return redirect('/home')->with('error', 'شما دسترسی به پنل مدیریت را ندارید');
    }
}
