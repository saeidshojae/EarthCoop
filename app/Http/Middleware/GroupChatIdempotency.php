<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class GroupChatIdempotency
{
    public function handle(Request $request, Closure $next)
    {
        if (! config('group-chat.features.idempotency_v1', true)) {
            return $next($request);
        }

        $key = trim((string) ($request->header('Idempotency-Key') ?: $request->input('idempotency_key')));
        if ($key === '' || ! Schema::hasTable('group_chat_idempotency_keys')) {
            return $next($request);
        }

        if (! preg_match('/^[A-Za-z0-9._:-]{8,100}$/', $key)) {
            return $this->error($request, 422, 'validation_failed', 'Invalid idempotency key.', false);
        }

        $scope = (string) ($request->route()?->getName() ?: $request->path());
        $userId = (int) $request->user()->id;
        $fingerprint = $this->fingerprint($request);

        try {
            DB::table('group_chat_idempotency_keys')->insert([
                'user_id' => $userId,
                'scope' => $scope,
                'idempotency_key' => $key,
                'request_hash' => $fingerprint,
                'state' => 'processing',
                'created_at' => now(),
                'updated_at' => now(),
                'expires_at' => now()->addDay(),
            ]);
        } catch (QueryException $exception) {
            $existing = DB::table('group_chat_idempotency_keys')
                ->where('user_id', $userId)->where('scope', $scope)->where('idempotency_key', $key)->first();
            if (! $existing) {
                throw $exception;
            }
            if (! hash_equals((string) $existing->request_hash, $fingerprint)) {
                return $this->error($request, 409, 'conflict', 'Idempotency key was reused with a different request.', false);
            }
            if ($existing->state !== 'completed') {
                return $this->error($request, 409, 'request_in_progress', 'The original request is still processing.', true)
                    ->header('Retry-After', '1');
            }

            return response()->json(json_decode((string) $existing->response_body, true) ?: [], (int) $existing->response_status)
                ->header('Idempotency-Replayed', 'true');
        }

        try {
            $response = $next($request);
            if ($response instanceof JsonResponse && $response->getStatusCode() < 500) {
                DB::table('group_chat_idempotency_keys')
                    ->where('user_id', $userId)->where('scope', $scope)->where('idempotency_key', $key)
                    ->update([
                        'state' => 'completed',
                        'response_status' => $response->getStatusCode(),
                        'response_body' => $response->getContent(),
                        'updated_at' => now(),
                    ]);
            } else {
                $this->release($userId, $scope, $key);
            }

            return $response;
        } catch (\Throwable $exception) {
            $this->release($userId, $scope, $key);
            throw $exception;
        }
    }

    private function fingerprint(Request $request): string
    {
        $input = $request->except(['_token', 'idempotency_key']);
        ksort($input);
        $files = collect($request->allFiles())->map(fn ($file) => [
            'size' => $file->getSize(),
            'mime' => $file->getMimeType(),
            'sha256' => hash_file('sha256', $file->getRealPath()),
        ])->all();

        return hash('sha256', json_encode([$input, $files], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
    }

    private function release(int $userId, string $scope, string $key): void
    {
        DB::table('group_chat_idempotency_keys')
            ->where('user_id', $userId)->where('scope', $scope)->where('idempotency_key', $key)->delete();
    }

    private function error(Request $request, int $status, string $code, string $message, bool $retryable): JsonResponse
    {
        $requestId = trim((string) $request->header('X-Request-ID')) ?: (string) Str::uuid();

        return response()->json([
            'status' => 'error',
            'data' => null,
            'error' => compact('code', 'message', 'retryable'),
            'meta' => ['api_version' => '2026-08-05', 'http_status' => $status],
            'request_id' => $requestId,
        ], $status)->header('X-Request-ID', $requestId);
    }
}
