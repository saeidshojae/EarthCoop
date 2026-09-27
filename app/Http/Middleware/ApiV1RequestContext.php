<?php

namespace App\Http\Middleware;

use App\Http\Support\Api\V1\ApiRequestContext;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ApiV1RequestContext
{
    public function handle(Request $request, Closure $next)
    {
        $context = new ApiRequestContext(
            $this->requestId($request->header('X-Request-ID')),
            $this->locale($request->header('Accept-Language')),
            $this->timezone($request->header('X-Timezone')),
            $this->deviceId($request->header('X-Device-ID')),
        );

        $request->attributes->set('api_v1_context', $context);
        app()->setLocale($context->locale());

        $response = $next($request);
        $response->headers->set('X-Request-ID', $context->requestId());
        $response->headers->set('Content-Language', $context->locale());

        return $response;
    }

    private function requestId(?string $candidate): string
    {
        $candidate = trim((string) $candidate);

        return $candidate !== '' && Str::isUuid($candidate)
            ? $candidate
            : (string) Str::uuid();
    }

    private function locale(?string $header): string
    {
        foreach (explode(',', strtolower((string) $header)) as $part) {
            $tag = trim(explode(';', $part, 2)[0]);
            $base = explode('-', $tag, 2)[0];
            if (in_array($base, ['fa', 'en', 'ar'], true)) {
                return $base;
            }
        }

        return 'fa';
    }

    private function timezone(?string $timezone): ?string
    {
        $timezone = trim((string) $timezone);

        return $timezone !== '' && in_array($timezone, timezone_identifiers_list(), true)
            ? $timezone
            : null;
    }

    private function deviceId(?string $deviceId): ?string
    {
        $deviceId = trim((string) $deviceId);

        return $deviceId !== '' && strlen($deviceId) <= 128 ? $deviceId : null;
    }
}
