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
use App\Modules\Secretariat\Contracts\SecretariatKnowledgeRanker;
use App\Modules\Secretariat\Contracts\SecretariatMalwareScanner;
use App\Modules\Secretariat\Services\DeterministicSecretariatKnowledgeRanker;
use App\Modules\Secretariat\Services\UnavailableSecretariatMalwareScanner;
use App\Modules\Stock\Services\AuctionService;
use App\Modules\Stock\Services\CanonicalAwareAuctionService;
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
use App\Services\NajmHoda\NajmHodaPrivateGroupMeetingCommandService;
use App\Services\NajmHoda\NajmHodaPrivateGroupMeetingDecisionCommandService;

class AppServiceProvider extends ServiceProvider
{
    public function register()
    {
        $this->app->bind(SubAccountService::class, SafeSubAccountService::class);
        $this->app->bind(TransactionService::class, StrictTransactionService::class);
        $this->app->bind(UserController::class, SafeUserController::class);
        $this->app->bind(NajmHodaPrivateGroupMeetingCommandService::class, NajmHodaPrivateGroupMeetingDecisionCommandService::class);
        $this->app->bind(SecretariatKnowledgeRanker::class, DeterministicSecretariatKnowledgeRanker::class);
        $this->app->bind(AuctionService::class, CanonicalAwareAuctionService::class);
        // Production deployments may replace this binding with ClamAV or another
        // scanner adapter. The default is explicitly unavailable, never fake-clean.
        $this->app->bind(SecretariatMalwareScanner::class, UnavailableSecretariatMalwareScanner::class);
    }

    public function boot()
    {
        ini_set('max_execution_time', 120);

        // Stock owns migrations outside Laravel's default database/migrations path.
        // Register them with the framework so migrate, migrate:fresh and
        // RefreshDatabase all build the same canonical Stock schema.
        $this->loadMigrationsFrom(base_path('app/Modules/Stock/Migrations'));

        app()->terminating(function () {
            if (function_exists('fastcgi_finish_request')) {
                fastcgi_finish_request();
            }
        });

        $this->registerGroupChatViewData();
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
     * Supply the canonical chat Blade with request data that historically came
     * from inline model queries. This preserves the exact rendering contract
     * while collapsing membership/block lookups and keeping database access out
     * of the presentation layer.
     */
    private function registerGroupChatViewData(): void
    {
        View::composer('groups.chat', function ($view): void {
            $data = $view->getData();
            $group = $data['group'] ?? null;
            $userId = (int) Auth::id();

            if (! $group instanceof Group || $userId <= 0) {
                return;
            }

            $membership = \App\Models\GroupUser::query()
                ->where('group_id', $group->id)
                ->where('user_id', $userId)
                ->first();

            $membershipCounts = DB::table('group_user')
                ->join('users', 'users.id', '=', 'group_user.user_id')
                ->where('group_user.group_id', $group->id)
                ->where('group_user.status', 1)
                ->where('users.is_system', false)
                ->selectRaw('SUM(CASE WHEN group_user.role = 4 THEN 1 ELSE 0 END) as guest_count')
                ->selectRaw('SUM(CASE WHEN group_user.role <> 4 THEN 1 ELSE 0 END) as member_count')
                ->first();

            $memberCount = (int) ($membershipCounts->member_count ?? 0);
            $guestCount = (int) ($membershipCounts->guest_count ?? 0);
            // The same Group instance is subsequently rendered by the information
            // panel. Store request-local aggregates on it so userCount()/guestsCount()
            // reuse this query instead of issuing their own counts.
            $group->setAttribute('active_members_count', $memberCount);
            $group->setAttribute('active_guests_count', $guestCount);

            $blocks = \App\Models\Block::query()
                ->where('user_id', $userId)
                ->whereIn('position', ['election', 'message', 'post', 'poll'])
                ->get()
                ->keyBy('position');

            $view->with([
                'memberCount' => $memberCount,
                'guestCount' => $guestCount,
                'blogCount' => (int) DB::table('blogs')->where('group_id', $group->id)->count(),
                'pollCount' => (int) DB::table('polls')->where('group_id', $group->id)->count(),
                'pivotUser' => $membership,
                'checkBlockElection' => $blocks->get('election'),
                'checkBlockMessage' => $blocks->get('message'),
                'checkBlockPost' => $blocks->get('post'),
                'checkBlockPoll' => $blocks->get('poll'),
            ]);
        });
    }

    /**
     * Measure the complete server-side cost of the canonical group chat page.
     * Local-only and read-only: it never changes chat behaviour.
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
            'patterns' => [],
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

            $pattern = preg_replace('/\bin\s*\([^)]*\)/iu', 'in (?)', $normalizedSql) ?: $normalizedSql;
            $pattern = preg_replace('/\?\s*,\s*\?(?:\s*,\s*\?)*/u', '?', $pattern) ?: $pattern;
            $pattern = mb_substr($pattern, 0, 500);
            if (! isset($stats['patterns'][$pattern])) {
                $stats['patterns'][$pattern] = ['count' => 0, 'sql_ms' => 0.0];
            }
            $stats['patterns'][$pattern]['count']++;
            $stats['patterns'][$pattern]['sql_ms'] += $time;
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

            $patterns = [];
            foreach ($stats['patterns'] as $sql => $data) {
                $patterns[] = [
                    'count' => (int) $data['count'],
                    'sql_ms' => round((float) $data['sql_ms'], 2),
                    'sql' => $sql,
                ];
            }
            usort($patterns, static function (array $a, array $b): int {
                $byCount = $b['count'] <=> $a['count'];
                return $byCount !== 0 ? $byCount : ($b['sql_ms'] <=> $a['sql_ms']);
            });
            $patterns = array_slice($patterns, 0, 12);

            $payload = [
                'group_id' => $groupId,
                'total_server_ms' => $totalMs,
                'query_count' => (int) $stats['query_count'],
                'sql_ms' => round((float) $stats['sql_ms'], 2),
                'non_sql_ms' => $totalMs !== null ? round(max(0, $totalMs - (float) $stats['sql_ms']), 2) : null,
                'top_query_patterns' => $patterns,
                'slowest_queries' => $stats['slowest'],
                'memory_peak_mb' => round(memory_get_peak_usage(true) / 1024 / 1024, 2),
            ];

            Log::info('[GROUP_CHAT_PERF] full request', $payload);

            $event->response->headers->set('X-EarthCoop-Server-Ms', (string) ($totalMs ?? 'n/a'));
            $event->response->headers->set('X-EarthCoop-Sql-Count', (string) $stats['query_count']);
            $event->response->headers->set('X-EarthCoop-Sql-Ms', (string) round((float) $stats['sql_ms'], 2));
        });
    }
}
