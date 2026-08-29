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

            Route::middleware([
                'web',
                \App\Http\Middleware\AdminMiddleware::class,
                \App\Http\Middleware\FounderOperationsMiddleware::class,
            ])->group(base_path('routes/najm-hoda-founder-ops.php'));

            Route::middleware('web')
                ->group(base_path('routes/web.php'));

            // Canonical authenticated My Groups route intentionally loads after
            // web.php so the legacy public /groups definition is shadowed. Guests
            // must be redirected before GroupController@index can dereference user().
            Route::middleware(['web', \App\Http\Middleware\Authenticate::class])
                ->group(base_path('routes/groups-index.php'));

            // Election compatibility/canonical routes intentionally load after
            // web.php so legacy responsibility-response endpoints are shadowed
            // by the E7 read-only confirmation + CSRF-protected POST flow.
            Route::middleware('web')
                ->group(base_path('routes/elections.php'));

            Route::middleware('web')
                ->group(base_path('routes/group-election-surface.php'));

            Route::middleware('web')
                ->group(base_path('routes/najm-hoda-group-attention.php'));

            Route::middleware('web')
                ->group(base_path('routes/najm-bahar.php'));

            Route::middleware('web')
                ->group(base_path('routes/secretariat.php'));

            // Canonical Stock admin write routes intentionally load last so the
            // legacy rial-priced create/edit endpoints in web.php are shadowed.
            Route::middleware('web')
                ->group(base_path('routes/stock-canonical-admin.php'));

            // Canonical Stock Book intentionally shadows the legacy book action so
            // opening the asset ledger never creates a money wallet or recalculates
            // legacy rial market data as a read-side effect.
            Route::middleware('web')
                ->group(base_path('routes/stock-canonical-book.php'));
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
