<?php

namespace App\Http\Support\Api\V1;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;

final class ApiResponse
{
    public static function success(mixed $data, int $status = 200, array $meta = []): JsonResponse
    {
        [$requestId, $locale] = self::context();

        return response()->json([
            'status' => 'success',
            'data' => $data,
            'error' => null,
            'meta' => array_merge(['api_version' => 'v1'], $meta),
            'request_id' => $requestId,
        ], $status)
            ->header('X-Request-ID', $requestId)
            ->header('Content-Language', $locale);
    }

    public static function error(
        string $code,
        string $message,
        int $status,
        mixed $details = null,
        bool $retryable = false,
        array $headers = [],
    ): JsonResponse {
        [$requestId, $locale] = self::context();

        $response = response()->json([
            'status' => 'error',
            'data' => null,
            'error' => [
                'code' => $code,
                'message' => $message,
                'details' => $details,
                'retryable' => $retryable,
            ],
            'meta' => [
                'api_version' => 'v1',
                'http_status' => $status,
            ],
            'request_id' => $requestId,
        ], $status, $headers);

        return $response
            ->header('X-Request-ID', $requestId)
            ->header('Content-Language', $locale);
    }

    private static function context(): array
    {
        $request = request();
        $context = $request->attributes->get('api_v1_context');

        if ($context instanceof ApiRequestContext) {
            return [$context->requestId(), $context->locale()];
        }

        $candidate = trim((string) $request->header('X-Request-ID'));
        $requestId = $candidate !== '' && Str::isUuid($candidate) ? $candidate : (string) Str::uuid();

        return [$requestId, app()->getLocale() ?: 'fa'];
    }
}
