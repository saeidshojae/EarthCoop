<?php

namespace App\Http\Middleware;

use App\Http\Support\Api\V1\ApiResponse;
use Closure;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ApiV1Idempotency
{
    public function handle(Request $request, Closure $next)
    {
        $key = trim((string) $request->header('Idempotency-Key'));

        if ($key === '') {
            return ApiResponse::error('idempotency_key_required', 'An Idempotency-Key header is required.', 422);
        }

        if (! preg_match('/^[A-Za-z0-9._:-]{8,100}$/', $key)) {
            return ApiResponse::error('validation_failed', 'Invalid idempotency key.', 422);
        }

        $user = $request->user();
        if (! $user) {
            return ApiResponse::error('unauthenticated', 'Authentication is required.', 401);
        }

        if (! Schema::hasTable('api_v1_idempotency_keys')) {
            return ApiResponse::error('idempotency_unavailable', 'Idempotency protection is temporarily unavailable.', 503, null, true);
        }

        $actorKey = 'user:' . $user->getAuthIdentifier();
        $scope = (string) ($request->route()?->getName() ?: $request->path());
        $fingerprint = $this->fingerprint($request);

        $claimed = false;
        for ($attempt = 0; $attempt < 2; $attempt++) {
            try {
                DB::table('api_v1_idempotency_keys')->insert([
                    'actor_key' => $actorKey,
                    'scope' => $scope,
                    'idempotency_key' => $key,
                    'request_hash' => $fingerprint,
                    'state' => 'processing',
                    'created_at' => now(),
                    'updated_at' => now(),
                    'expires_at' => now()->addDay(),
                ]);
                $claimed = true;
                break;
            } catch (QueryException $exception) {
                $existing = DB::table('api_v1_idempotency_keys')
                    ->where('actor_key', $actorKey)
                    ->where('scope', $scope)
                    ->where('idempotency_key', $key)
                    ->first();

                if (! $existing) {
                    throw $exception;
                }

                if ($existing->expires_at && now()->greaterThan($existing->expires_at)) {
                    DB::table('api_v1_idempotency_keys')->where('id', $existing->id)->delete();
                    continue;
                }

                if (! hash_equals((string) $existing->request_hash, $fingerprint)) {
                    return ApiResponse::error(
                        'idempotency_key_reused',
                        'Idempotency key was reused with a different request.',
                        409,
                    );
                }

                if ($existing->state !== 'completed') {
                    return ApiResponse::error(
                        'request_in_progress',
                        'The original request is still processing.',
                        409,
                        null,
                        true,
                    )->header('Retry-After', '1');
                }

                return response()->json(
                    json_decode((string) $existing->response_body, true) ?: [],
                    (int) $existing->response_status,
                )->header('Idempotency-Replayed', 'true');
            }
        }

        if (! $claimed) {
            return ApiResponse::error('idempotency_unavailable', 'Idempotency protection is temporarily unavailable.', 503, null, true);
        }

        try {
            $response = $next($request);

            // A temporary throttle must not permanently poison an unchanged retry intent.
            if ($response instanceof JsonResponse && $response->getStatusCode() < 500 && $response->getStatusCode() !== 429) {
                DB::table('api_v1_idempotency_keys')
                    ->where('actor_key', $actorKey)
                    ->where('scope', $scope)
                    ->where('idempotency_key', $key)
                    ->update([
                        'state' => 'completed',
                        'response_status' => $response->getStatusCode(),
                        'response_body' => $response->getContent(),
                        'updated_at' => now(),
                    ]);
            } else {
                $this->release($actorKey, $scope, $key);
            }

            return $response;
        } catch (\Throwable $exception) {
            $this->release($actorKey, $scope, $key);
            throw $exception;
        }
    }

    private function fingerprint(Request $request): string
    {
        // Request::except() is based on Request::all(), which merges uploaded
        // files into the payload. UploadedFile objects are not JSON serializable
        // and are fingerprinted separately below using stable content metadata.
        $input = $request->input();
        unset($input['_token'], $input['idempotency_key']);
        $this->recursiveSort($input);

        $files = [];
        $this->flattenFiles($request->allFiles(), $files);

        return hash('sha256', json_encode([$input, $files], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
    }

    private function recursiveSort(array &$value): void
    {
        ksort($value);
        foreach ($value as &$item) {
            if (is_array($item)) {
                $this->recursiveSort($item);
            }
        }
    }

    private function flattenFiles(array $files, array &$output, string $prefix = ''): void
    {
        foreach ($files as $name => $file) {
            $path = $prefix === '' ? (string) $name : $prefix . '.' . $name;
            if (is_array($file)) {
                $this->flattenFiles($file, $output, $path);
                continue;
            }

            $output[$path] = [
                'size' => $file->getSize(),
                'mime' => $file->getMimeType(),
                'sha256' => hash_file('sha256', $file->getRealPath()),
            ];
        }
        ksort($output);
    }

    private function release(string $actorKey, string $scope, string $key): void
    {
        DB::table('api_v1_idempotency_keys')
            ->where('actor_key', $actorKey)
            ->where('scope', $scope)
            ->where('idempotency_key', $key)
            ->delete();
    }
}
