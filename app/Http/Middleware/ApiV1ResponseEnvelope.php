<?php

namespace App\Http\Middleware;

use App\Http\Support\Api\V1\ApiRequestContext;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ApiV1ResponseEnvelope
{
    public function handle(Request $request, Closure $next)
    {
        $response = $next($request);
        $context = $request->attributes->get('api_v1_context');
        $requestId = $context instanceof ApiRequestContext ? $context->requestId() : (string) Str::uuid();
        $locale = $context instanceof ApiRequestContext ? $context->locale() : 'fa';

        if ($response instanceof JsonResponse && $response->getStatusCode() < 400 && $response->getStatusCode() !== 204) {
            $payload = $response->getData(true);

            if (! $this->isNormalized($payload)) {
                $response->setData([
                    'status' => 'success',
                    'data' => $payload,
                    'error' => null,
                    'meta' => ['api_version' => 'v1'],
                    'request_id' => $requestId,
                ]);
            }
        }

        $response->headers->set('X-Request-ID', $requestId);
        $response->headers->set('Content-Language', $locale);

        return $response;
    }

    private function isNormalized(mixed $payload): bool
    {
        return is_array($payload)
            && in_array($payload['status'] ?? null, ['success', 'error'], true)
            && array_key_exists('data', $payload)
            && array_key_exists('error', $payload)
            && isset($payload['meta']['api_version'])
            && array_key_exists('request_id', $payload);
    }
}
