<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Session;

class SetLocale
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure(\Illuminate\Http\Request): (\Illuminate\Http\Response|\Illuminate\Http\RedirectResponse)  $next
     * @return \Illuminate\Http\Response|\Illuminate\Http\RedirectResponse
     */
    public function handle(Request $request, Closure $next)
    {
        // زبان‌های پشتیبانی شده
        $availableLocales = ['fa', 'en', 'ar'];
        
        // دریافت زبان از session یا استفاده از مقدار پیش‌فرض برنامه
        $locale = Session::get('locale');

        // handle corrupted or non-string locale values gracefully
        if ($locale && !is_string($locale)) {
            \Log::warning('Invalid locale value detected', [
                'type' => gettype($locale),
                'class' => is_object($locale) ? get_class($locale) : null,
            ]);
            $locale = null;
            Session::forget('locale');
        }
        
        // اگر زبان در session نبود، از مقدار پیش‌فرض برنامه استفاده می‌کنیم
        if (!$locale) {
            $locale = config('app.locale');
        }
        
        // اطمینان از اینکه زبان معتبر است
        if (!$locale || !in_array($locale, $availableLocales, true)) {
            $locale = config('app.locale');
        }
        
        // تنظیم زبان برنامه
        App::setLocale($locale);
        
        // تنظیم direction برای استفاده در view ها
        $direction = in_array($locale, ['fa', 'ar'], true) ? 'rtl' : 'ltr';
        view()->share('currentLocale', $locale);
        view()->share('direction', $direction);
        
        return $next($request);
    }
}
