<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class GroupChatTiming
{
    public function handle(Request $request, Closure $next): Response
    {
        if (!(bool) config('group-chat.api_timing.enabled', true)) {
            return $next($request);
        }

        $start = microtime(true);
        $response = $next($request);
        $elapsedMs = (int) round((microtime(true) - $start) * 1000);

        $response->headers->set('X-Chat-Server-Time-Ms', (string) $elapsedMs);
        $response->headers->set('Server-Timing', 'app;dur=' . $elapsedMs);

        $shouldLogSlow = $elapsedMs >= (int) config('group-chat.api_timing.slow_ms', 1200);
        $shouldLogAll = (bool) config('group-chat.api_timing.log', false);
        if ($shouldLogSlow || $shouldLogAll) {
            Log::info('group_chat_timing', [
                'path' => $request->path(),
                'method' => $request->method(),
                'status' => $response->getStatusCode(),
                'duration_ms' => $elapsedMs,
                'group_id' => $request->route('group')?->id ?? $request->input('group_id'),
                'user_id' => optional($request->user())->id,
            ]);
        }

        return $response;
    }
}
