<?php

namespace App\Http;

use App\Http\Middleware\CheckAuth;
use Illuminate\Foundation\Http\Kernel as HttpKernel;

class Kernel extends HttpKernel
{
    protected $middleware = [
        // \App\Http\Middleware\TrustHosts::class,
        \App\Http\Middleware\TrustProxies::class,
        \Illuminate\Http\Middleware\HandleCors::class,
        \App\Http\Middleware\PreventRequestsDuringMaintenance::class,
        \Illuminate\Foundation\Http\Middleware\ValidatePostSize::class,
        \App\Http\Middleware\TrimStrings::class,
        \Illuminate\Foundation\Http\Middleware\ConvertEmptyStringsToNull::class,
    ];

    protected $middlewareGroups = [
        'web' => [
            \App\Http\Middleware\EncryptCookies::class,
            \Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse::class,
            \Illuminate\Session\Middleware\StartSession::class,
            \App\Http\Middleware\RejectInteractiveSystemIdentity::class,
            \Illuminate\View\Middleware\ShareErrorsFromSession::class,
            \App\Http\Middleware\VerifyCsrfToken::class,
            \Illuminate\Routing\Middleware\SubstituteBindings::class,
            \App\Http\Middleware\UpdateLastSeen::class,
            \App\Http\Middleware\SetLocale::class,
        ],

        'api' => [
            \Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful::class,
            // 'throttle' => \Illuminate\Routing\Middleware\ThrottleRequests::class,
            \Illuminate\Routing\Middleware\SubstituteBindings::class,
        ],
    ];

    protected $routeMiddleware = [
        'check.auth' => \App\Http\Middleware\CheckAuth::class,
        'auth' => \App\Http\Middleware\Authenticate::class,
        'auth.basic' => \Illuminate\Auth\Middleware\AuthenticateWithBasicAuth::class,
        'auth.session' => \Illuminate\Session\Middleware\AuthenticateSession::class,
        'cache.headers' => \Illuminate\Http\Middleware\SetCacheHeaders::class,
        'can' => \Illuminate\Auth\Middleware\Authorize::class,
        'guest' => \App\Http\Middleware\RedirectIfAuthenticated::class,
        'password.confirm' => \Illuminate\Auth\Middleware\RequirePassword::class,
        'signed' => \App\Http\Middleware\ValidateSignature::class,
        'throttle' => \Illuminate\Routing\Middleware\ThrottleRequests::class,
        'verified' => \App\Http\Middleware\EnsureEmailIsVerified::class,
        'admin' => \App\Http\Middleware\AdminMiddleware::class,
        'permission' => \App\Http\Middleware\PermissionMiddleware::class,
        'api.v1.context' => \App\Http\Middleware\ApiV1RequestContext::class,
        'api.v1.envelope' => \App\Http\Middleware\ApiV1ResponseEnvelope::class,
        'api.v1.idempotency' => \App\Http\Middleware\ApiV1Idempotency::class,
        'api.v1.device' => \App\Http\Middleware\ApiV1DeviceSession::class,
        'api.v1.project-owner-authority' => \App\Http\Middleware\ApiV1ProjectOwnerAuthority::class,

        'email.verified' => \App\Http\Middleware\EnsureEmailIsVerified::class,
        'update.lastseen.logout' => \App\Http\Middleware\UpdateLastSeenOnLogout::class,
        'group.chat.timing' => \App\Http\Middleware\GroupChatTiming::class,
        'group.chat.csp' => \App\Http\Middleware\GroupChatContentSecurityPolicy::class,
        'group.chat.context' => \App\Http\Middleware\GroupChatRequestContext::class,
        'group.chat.idempotency' => \App\Http\Middleware\GroupChatIdempotency::class,
        'group.session.writable' => \App\Http\Middleware\EnsureGroupSessionWritable::class,
    ];
}
