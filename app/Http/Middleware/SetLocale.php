<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Session;

class SetLocale
{
    private static ?bool $canPersistUserLocale = null;

    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure(\Illuminate\Http\Request): (\Illuminate\Http\Response|\Illuminate\Http\RedirectResponse)  $next
     * @return \Illuminate\Http\Response|\Illuminate\Http\RedirectResponse
     */
    public function handle(Request $request, Closure $next)
    {
        $availableLocales = ['fa', 'en', 'ar'];
        $user = $request->user();
        $sessionLocale = Session::get('locale');

        if ($sessionLocale && ! is_string($sessionLocale)) {
            \Log::warning('Invalid locale value detected', [
                'type' => gettype($sessionLocale),
                'class' => is_object($sessionLocale) ? get_class($sessionLocale) : null,
            ]);
            $sessionLocale = null;
            Session::forget('locale');
        }

        $storedLocale = is_string($user?->locale ?? null) ? $user->locale : null;
        $locale = $sessionLocale ?: $storedLocale ?: config('app.locale');

        if (! is_string($locale) || ! in_array($locale, $availableLocales, true)) {
            $locale = config('app.locale');
        }

        App::setLocale($locale);

        if (
            is_string($sessionLocale)
            && in_array($sessionLocale, $availableLocales, true)
            && $user
            && $user->locale !== $sessionLocale
            && $this->canPersistUserLocale()
        ) {
            $user->forceFill(['locale' => $sessionLocale])->saveQuietly();
        }

        $direction = in_array($locale, ['fa', 'ar'], true) ? 'rtl' : 'ltr';
        view()->share('currentLocale', $locale);
        view()->share('direction', $direction);

        $response = $next($request);

        if ($request->cookie('earthcoop_locale') !== $locale) {
            $response->headers->setCookie(cookie(
                'earthcoop_locale',
                $locale,
                525600,
                '/',
                null,
                $request->isSecure(),
                false,
                false,
                'lax',
            ));
        }

        return $response;
    }

    private function canPersistUserLocale(): bool
    {
        if (self::$canPersistUserLocale !== null) {
            return self::$canPersistUserLocale;
        }

        try {
            return self::$canPersistUserLocale = Schema::hasColumn('users', 'locale');
        } catch (\Throwable) {
            return self::$canPersistUserLocale = false;
        }
    }
}
