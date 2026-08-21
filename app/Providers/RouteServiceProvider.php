<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;

class RouteServiceProvider extends ServiceProvider
{
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

            Route::middleware(['web', \App\Http\Middleware\AdminMiddleware::class])
                ->group(base_path('routes/najm-hoda-founder-ops.php'));

            Route::middleware('web')
                ->group(base_path('routes/web.php'));

            Route::middleware('web')
                ->group(base_path('routes/najm-hoda-group-attention.php'));

            Route::middleware('web')
                ->group(base_path('routes/najm-bahar.php'));

            Route::middleware('web')
                ->group(base_path('routes/secretariat.php'));
        });
    }

    protected function configureRateLimiting()
    {
        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(60)->by('api:' . ($request->user()?->id ?: $request->ip()));
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

        foreach ([
            'group-message' => 30,
            'group-upload' => 10,
            'group-post' => 6,
            'group-poll' => 6,
            'group-vote' => 30,
            'group-comment' => 20,
            'group-reaction' => 60,
        ] as $name => $attempts) {
            RateLimiter::for($name, function (Request $request) use ($name, $attempts) {
                return Limit::perMinute($attempts)->by($name . ':' . ($request->user()?->id ?: $request->ip()));
            });
        }
    }
}
