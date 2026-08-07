<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;

class RouteServiceProvider extends ServiceProvider
{
    /**
     * @var string
     */
    public const HOME = '/home';

    public function boot()
    {
        $this->configureRateLimiting();

        $this->routes(function () {
            Route::middleware('api')
                ->prefix('api')
                ->group(base_path('routes/api.php'));

            Route::middleware('api')
                ->prefix('api')
                ->group(base_path('routes/najm-hoda-n8n.php'));

            Route::middleware(['web', \App\Http\Middleware\AdminMiddleware::class])
                ->prefix('admin/najm-hoda/n8n')
                ->name('admin.najm-hoda.n8n.')
                ->group(base_path('routes/najm-hoda-admin-n8n.php'));

            Route::middleware('web')
                ->group(base_path('routes/web.php'));

            Route::middleware('web')
                ->group(base_path('routes/najm-bahar.php'));
        });
    }

    protected function configureRateLimiting()
    {
        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(60)->by($request->user()?->id ?: $request->ip());
        });

        RateLimiter::for('najm-hoda-autonomy-read', function (Request $request) {
            return Limit::perMinute(60)->by('nh-read:' . ($request->user()?->id ?: $request->ip()));
        });

        RateLimiter::for('najm-hoda-autonomy-write', function (Request $request) {
            return Limit::perMinute(20)->by('nh-write:' . ($request->user()?->id ?: $request->ip()));
        });

        RateLimiter::for('najm-hoda-n8n-callback', function (Request $request) {
            return Limit::perMinute(30)->by('nh-n8n-callback:' . $request->ip());
        });
    }
}
