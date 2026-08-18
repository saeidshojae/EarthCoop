<?php

namespace App\Providers;

// use Illuminate\Support\Facades\Gate;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * The model to policy mappings for the application.
     *
     * @var array<class-string, class-string>
     */
    protected $policies = [
        \App\Models\Group::class => \App\Policies\GroupPolicy::class,
        \App\Models\Message::class => \App\Policies\MessagePolicy::class,
        \App\Models\Blog::class => \App\Policies\PostPolicy::class,
        \App\Models\Poll::class => \App\Policies\PollPolicy::class,
        \App\Models\Comment::class => \App\Policies\CommentPolicy::class,
        \App\Modules\NajmBahar\Models\Project::class => \App\Policies\NajmBahar\ProjectPolicy::class,
        \App\Modules\NajmBahar\Models\Investment::class => \App\Policies\NajmBahar\InvestmentPolicy::class,
        \App\Modules\Secretariat\Models\SecretariatOffice::class => \App\Modules\Secretariat\Policies\SecretariatOfficePolicy::class,
        \App\Modules\Secretariat\Models\SecretariatRecord::class => \App\Modules\Secretariat\Policies\SecretariatRecordPolicy::class,
        \App\Modules\Secretariat\Models\SecretariatCase::class => \App\Modules\Secretariat\Policies\SecretariatCasePolicy::class,
    ];

    /**
     * Register any authentication / authorization services.
     *
     * @return void
     */
    public function boot()
    {
        $this->registerPolicies();

        //
    }
}
