<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Auth;
use App\Modules\NajmBahar\Models\Transaction as NajmTransaction;
use App\Modules\NajmBahar\Services\SubAccountService;
use App\Modules\NajmBahar\Services\SafeSubAccountService;
use App\Modules\NajmBahar\Services\TransactionService;
use App\Modules\NajmBahar\Services\SafeTransactionService;
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
        // Release A transition: preserve public service contracts while routing
        // unsafe legacy Main ↔ Sub balance mutations through canonical ledger-
        // backed implementations. These bindings are transitional and can be
        // removed once the legacy services themselves are fully normalized.
        $this->app->bind(SubAccountService::class, SafeSubAccountService::class);
        $this->app->bind(TransactionService::class, SafeTransactionService::class);
    }

    public function boot()
    {
        ini_set('max_execution_time', 120);

        app()->terminating(function () {
            if (function_exists('fastcgi_finish_request')) {
                fastcgi_finish_request();
            }
        });

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
}
