<?php

namespace App\Http\Middleware;

use App\Http\Support\Api\V1\ApiResponse;
use App\Models\Group;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class PrepareNativeGroupMessage
{
    public function handle(Request $request, Closure $next)
    {
        $group = $request->route('group');
        abort_unless($group instanceof Group, 404);
        // Include the resource in the fingerprint BEFORE the idempotency middleware.
        $request->merge(['group_id' => (int) $group->id]);
        $response = $next($request);
        if (! $response instanceof JsonResponse || $response->getStatusCode() < 400) {
            return $response;
        }
        $body = $response->getData(true);
        if (($body['meta']['api_version'] ?? null) === 'v1') {
            return $response;
        }
        $status = $response->getStatusCode();
        return ApiResponse::error(
            $body['error']['code'] ?? $body['code'] ?? 'group_message_failed',
            $body['error']['message'] ?? $body['message'] ?? 'امکان ارسال پیام وجود ندارد.',
            $status,
            $body['error']['details'] ?? $body['errors'] ?? null,
            $status === 429 || $status >= 500,
            $response->headers->has('Retry-After') ? ['Retry-After' => $response->headers->get('Retry-After')] : [],
        );
    }
}
