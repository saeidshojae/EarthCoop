<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class GroupChatRequestContext
{
    public function handle(Request $request, Closure $next)
    {
        $requestId = $this->requestId($request);
        $request->attributes->set('group_chat_request_id', $requestId);

        $response = $next($request);
        $response->headers->set('X-Request-ID', $requestId);

        if (! $response instanceof JsonResponse || ! config('group-chat.features.api_envelope_v1', true)) {
            return $response;
        }

        $payload = $response->getData(true);
        $payload = is_array($payload) ? $payload : ['result' => $payload];
        $successful = $response->isSuccessful();
        $legacyStatus = $payload['status'] ?? null;
        $status = $successful && $legacyStatus !== 'error' ? 'success' : 'error';

        $payload['status'] = $status;
        $payload['data'] ??= $successful ? $this->legacyData($payload) : null;
        $payload['error'] ??= $successful ? null : [
            'code' => $this->errorCode($response->getStatusCode()),
            'message' => $this->errorMessage($payload, $response->getStatusCode()),
            'details' => $payload['errors'] ?? null,
            'retryable' => $response->getStatusCode() >= 500 || $response->getStatusCode() === 429,
        ];
        $payload['meta'] = array_merge([
            'api_version' => '2026-08-05',
            'http_status' => $response->getStatusCode(),
        ], is_array($payload['meta'] ?? null) ? $payload['meta'] : []);
        $payload['request_id'] = $requestId;

        $response->setData($payload);

        return $response;
    }

    private function requestId(Request $request): string
    {
        $candidate = trim((string) $request->header('X-Request-ID'));

        return preg_match('/^[A-Za-z0-9._:-]{8,100}$/', $candidate)
            ? $candidate
            : (string) Str::uuid();
    }

    private function legacyData(array $payload): array
    {
        return collect($payload)
            ->except(['status', 'data', 'error', 'meta', 'request_id', 'message', 'errors'])
            ->all();
    }

    private function errorCode(int $status): string
    {
        return match ($status) {
            400 => 'bad_request',
            401 => 'unauthenticated',
            403 => 'forbidden',
            404 => 'not_found',
            409 => 'conflict',
            413 => 'payload_too_large',
            422 => 'validation_failed',
            429 => 'rate_limited',
            default => $status >= 500 ? 'server_error' : 'request_failed',
        };
    }

    private function errorMessage(array $payload, int $status): string
    {
        $message = $payload['message'] ?? null;
        if (is_string($message) && trim($message) !== '') {
            return $message;
        }

        return match ($status) {
            403 => 'You are not allowed to perform this operation.',
            404 => 'The requested resource was not found.',
            422 => 'The submitted data is invalid.',
            429 => 'Too many requests.',
            default => 'The operation could not be completed.',
        };
    }
}
