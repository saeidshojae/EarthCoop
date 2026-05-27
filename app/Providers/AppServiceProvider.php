<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Auth;
use App\Modules\NajmBahar\Models\Transaction as NajmTransaction;
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
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        //
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        // افزایش execution time برای صفحات سنگین مانند چت گروه
        ini_set('max_execution_time', 120);

        // اطمینان از اینکه response قبل از afterResponse callbacks به browser ارسال می‌شه
        // این مهم است تا NajmHoda و سایر پردازش‌های سنگین پس از پاسخ، کاربر را منتظر نگذارند
        app()->terminating(function () {
            if (function_exists('fastcgi_finish_request')) {
                fastcgi_finish_request();
            }
        });

        // Register Blade namespace for module views
        View::addNamespace('Stock', base_path('app/Modules/Stock/Views'));
        View::addNamespace('Blog', base_path('app/Modules/Blog/Views'));

        // Blade directive برای چک کردن دسترسی
        Blade::if('hasPermission', function ($permission) {
            $user = Auth::user();
            return $user && $user->hasPermission($permission);
        });

        // Blade directive برای چک کردن نقش
        Blade::if('hasRole', function ($role) {
            $user = Auth::user();
            return $user && $user->hasRole($role);
        });

        // Blade directive برای چک کردن Super Admin
        Blade::if('isSuperAdmin', function () {
            $user = Auth::user();
            return $user && ($user->is_admin || $user->hasRole('super-admin'));
        });

        // Register Observer for NajmBahar Transactions
        NajmTransaction::observe(NajmBaharTransactionObserver::class);

        // Register Observer for Group legal accounts
        Group::observe(GroupObserver::class);

        // Register Observer for Knowledge Base Articles
        // This automatically invalidates Steward Agent cache when KB articles change
        KbArticle::observe(KbArticleObserver::class);

        // Register Observer for Blog Posts
        // Steward Agent uses blog content for contextual guidance
        Blog::observe(BlogObserver::class);

        // Register Observer for FAQ Questions
        // Steward Agent references FAQ answers for common questions
        FaqQuestion::observe(FaqQuestionObserver::class);

        // Register Observer for Steward Knowledge Files
        // Invalidates cache when uploaded files are added, updated, or deleted
        StewardKnowledgeFile::observe(StewardKnowledgeFileObserver::class);
    }
}
