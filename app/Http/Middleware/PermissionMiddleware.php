<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class PermissionMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next, ...$permissions): Response
    {
        if (!Auth::check()) {
            return redirect('/home')->with('error', 'لطفا ابتدا وارد شوید');
        }

        $user = Auth::user();

        // اگر Super Admin است، اجازه دسترسی دارد
        if ($user->is_admin || $user->hasRole('super-admin')) {
            return $next($request);
        }

        // اگر permissions خالی است، فقط admin بودن کافی است
        if (empty($permissions)) {
            return $next($request);
        }

        // بررسی دسترسی‌ها (OR: اگر هر کدام از permissions را داشته باشد، اجازه دارد)
        $hasPermission = false;
        foreach ($permissions as $permission) {
            if ($user->hasPermission($permission)) {
                $hasPermission = true;
                break;
            }
        }

        if (!$hasPermission) {
            abort(403, 'شما دسترسی به این بخش را ندارید');
        }

        return $next($request);
    }
}
