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

        // اگر Super Admin است یا دارای نقش ادمین است، اجازه دسترسی دارد
        if ($user->is_admin || $user->hasRole('super-admin') || $user->roles()->count() > 0) {
            return $next($request);
        }

        return redirect('/home')->with('error', 'شما دسترسی به پنل مدیریت را ندارید');
    }
}