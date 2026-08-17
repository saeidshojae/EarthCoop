<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Http\Events\RequestHandled;
use App\Http\Controllers\Admin\SafeUserController;
use App\Http\Controllers\Admin\UserController;
use App\Modules\NajmBahar\Models\Transaction as NajmTransaction;
use App\Modules\NajmBahar\Services\SubAccountService;
use App\Modules\NajmBahar\Services\SafeSubAccountService;
use App\Modules\NajmBahar\Services\TransactionService;
use App\Modules\NajmBahar\Services\StrictTransactionService;
use App\Observers\NajmBaharTransactionObserver;
use App\Models\Group;
use App\Observers\GroupObserver;
use App\Models\KbArticle;
use App\Observers\KbArticleObserver;
use App\Models\Blog;
use App\Observers\BlogObserver;
use App\Models\FaqQuestion;
use App\Observers\FaqQuestionObserver;
use App\Models\StewardKnowledgeFile;
use App\Observers\StewardKnowledgeFileObserver;

class AppServiceProvider extends ServiceProvider
{
    public function register()
    {
        // Release A-D transition: preserve public service contracts while routing
        // monetary mutations through explicit canonical boundaries. The strict
        // transaction adapter keeps system compatibility but fails closed for
        // any generic main economic-actor transfer that has no canonical route.
        $this->app->bind(SubAccountService::class, SafeSubAccountService::class);
        $this->app->bind(TransactionService::class, StrictTransactionService::class);

        // Release C/D transition: admin removal routes through the lifecycle-safe
        // controller. The destructive legacy purge implementation was physically
        // retired in Release D.
        $this->app->bind(UserController::class, SafeUserController::class);
    }

    public function boot()
    {
        ini_set('max_execution_time', 120);

        app()->terminating(function () {
            if (function_exists('fastcgi_finish_request')) {
                fastcgi_finish_request();
            }
        });

        $this->registerGroupChatPerformanceInstrumentation();

        View::addNamespace('Stock', base_path('app/Modules/Stock/Views'));
        View::addNamespace('Blog', base_path('app/Modules/Blog/Views'));

        Blade::if('hasPermission', function ($permission) {
            $user = Auth::user();
            return $user && $user->hasPermission($permission);
        });

        Blade::if('hasRole', function ($role) {
            $user = Auth::user();
            return $user && $user->hasRole($role);
        });

        Blade::if('isSuperAdmin', function () {
            $user = Auth::user();
            return $user && ($user->is_admin || $user->hasRole('super-admin'));
        });

        NajmTransaction::observe(NajmBaharTransactionObserver::class);
        Group::observe(GroupObserver::class);
        KbArticle::observe(KbArticleObserver::class);
        Blog::observe(BlogObserver::class);
        FaqQuestion::observe(FaqQuestionObserver::class);
        StewardKnowledgeFile::observe(StewardKnowledgeFileObserver::class);
    }

    /**
     * Measure the complete server-side cost of the canonical group chat page.
     *
     * Historical T1..T6 controller logs stop before Blade rendering, so they
     * cannot explain cases where the browser receives the page very late. This
     * local-only probe records the complete request duration, query count,
     * aggregate SQL time and the five slowest SQL statements without changing
     * any group-chat business behaviour.
     */
    private function registerGroupChatPerformanceInstrumentation(): void
    {
        if (! app()->environment('local')) {
            return;
        }

        $stats = [
            'query_count' => 0,
            'sql_ms' => 0.0,
            'slowest' => [],
        ];

        DB::listen(function (QueryExecuted $query) use (&$stats): void {
            $request = request();
            if (! $request || ! $request->routeIs('groups.chat')) {
                return;
            }

            $time = (float) $query->time;
            $stats['query_count']++;
            $stats['sql_ms'] += $time;

            $normalizedSql = preg_replace('/\s+/u', ' ', trim((string) $query->sql)) ?: (string) $query->sql;
            $stats['slowest'][] = [
                'ms' => round($time, 2),
                'sql' => mb_substr($normalizedSql, 0, 700),
            ];

            usort($stats['slowest'], static fn (array $a, array $b): int => $b['ms'] <=> $a['ms']);
            if (count($stats['slowest']) > 5) {
                $stats['slowest'] = array_slice($stats['slowest'], 0, 5);
            }
        });

        Event::listen(RequestHandled::class, function (RequestHandled $event) use (&$stats): void {
            if (! $event->request->routeIs('groups.chat')) {
                return;
            }

            $totalMs = defined('LARAVEL_START')
                ? round((microtime(true) - LARAVEL_START) * 1000, 2)
                : null;

            $group = $event->request->route('group');
            $groupId = is_object($group) && isset($group->id)
                ? (int) $group->id
                : (is_numeric($group) ? (int) $group : null);

            $payload = [
                'group_id' => $groupId,
                'total_server_ms' => $totalMs,
                'query_count' => (int) $stats['query_count'],
                'sql_ms' => round((float) $stats['sql_ms'], 2),
                'non_sql_ms' => $totalMs !== null ? round(max(0, $totalMs - (float) $stats['sql_ms']), 2) : null,
                'slowest_queries' => $stats['slowest'],
                'memory_peak_mb' => round(memory_get_peak_usage(true) / 1024 / 1024, 2),
            ];

            Log::info('[GROUP_CHAT_PERF] full request', $payload);

            // Expose the same coarse measurements in DevTools without requiring
            // any UI change. These headers are emitted only in local development.
            $event->response->headers->set('X-EarthCoop-Server-Ms', (string) ($totalMs ?? 'n/a'));
            $event->response->headers->set('X-EarthCoop-Sql-Count', (string) $stats['query_count']);
            $event->response->headers->set('X-EarthCoop-Sql-Ms', (string) round((float) $stats['sql_ms'], 2));
        });
    }
}
