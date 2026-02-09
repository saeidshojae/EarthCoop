<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Auth;
use App\Models\Spring;
use App\Modules\NajmBahar\Adapters\LegacyNajmAdapter;
use App\Modules\NajmBahar\Models\Transaction as NajmTransaction;
use App\Observers\NajmBaharTransactionObserver;
use App\Models\Group;
use App\Observers\GroupObserver;

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

        // Legacy Spring adapter removed - financial operations now handled in NajmBaharController

        // Register Observer for NajmBahar Transactions
        NajmTransaction::observe(NajmBaharTransactionObserver::class);

        // Register Observer for Group legal accounts
        Group::observe(GroupObserver::class);
    }
}
