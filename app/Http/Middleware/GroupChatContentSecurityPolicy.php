<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class GroupChatContentSecurityPolicy
{
    public function handle(Request $request, Closure $next)
    {
        $response = $next($request);

        $localViteScriptSources = [];
        $localViteStyleSources = [];
        $localViteConnectSources = [];

        if (app()->environment('local')) {
            $localViteScriptSources = [
                'http://localhost:5173',
                'http://127.0.0.1:5173',
            ];
            $localViteStyleSources = $localViteScriptSources;
            $localViteConnectSources = [
                'http://localhost:5173',
                'http://127.0.0.1:5173',
                'ws://localhost:5173',
                'ws://127.0.0.1:5173',
            ];
        }

        $scriptSrc = implode(' ', array_merge(["'self'", "'unsafe-inline'", "'unsafe-eval'"], $localViteScriptSources));
        $styleSrc = implode(' ', array_merge(["'self'", "'unsafe-inline'"], $localViteStyleSources));
        $connectSrc = implode(' ', array_merge(["'self'", 'ws:', 'wss:'], $localViteConnectSources));

        $response->headers->set('Content-Security-Policy', implode('; ', [
            "default-src 'self'",
            "script-src {$scriptSrc}",
            "style-src {$styleSrc}",
            "img-src 'self' data: blob:",
            "media-src 'self' blob:",
            "font-src 'self' data:",
            "connect-src {$connectSrc}",
            "frame-src 'none'",
            "object-src 'none'",
            "base-uri 'self'",
            "form-action 'self'",
        ]));
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');

        return $response;
    }
}
