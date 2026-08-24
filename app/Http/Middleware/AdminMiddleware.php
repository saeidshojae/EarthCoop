<?php
namespace App\Http\Middleware;

use Closure;
use Illuminate\Support\Facades\Auth;

class AdminMiddleware
{
    public function handle($request, Closure $next)
    {
        if (!Auth::check()) {
            return redirect('/home')->with('error', 'لطفا ابتدا وارد شوید');
        }

        $user = Auth::user();

        // Global admin surfaces, including Founder Operations, must not become
        // available merely because a user has an application role. Scoped roles
        // such as support, moderator, group-manager or content-manager are not
        // equivalent to EarthCoop administration authority.
        if ($user->is_admin || $user->hasRole('super-admin')) {
            return $next($request);
        }

        return redirect('/home')->with('error', 'شما دسترسی به پنل مدیریت را ندارید');
    }
}
