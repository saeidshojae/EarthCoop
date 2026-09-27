<?php

namespace App\Http\Middleware;

use App\Http\Support\Api\V1\ApiResponse;
use App\Models\NativeDevice;
use Closure;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;

class ApiV1DeviceSession
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();
        $token = $user?->currentAccessToken();

        if (! $user || ! $token instanceof PersonalAccessToken || ! $token->can('native') || ! $token->native_device_id) {
            return ApiResponse::error('unauthenticated', 'Authentication is required.', 401);
        }

        $device = NativeDevice::query()->find($token->native_device_id);
        if (! $device || (int) $device->user_id !== (int) $user->getAuthIdentifier() || $device->revoked_at !== null) {
            return ApiResponse::error('invalid_native_session', 'Native session is no longer valid.', 401);
        }

        $claimedDeviceId = trim((string) $request->header('X-Device-ID'));
        if ($claimedDeviceId !== '' && ! hash_equals((string) $device->public_id, $claimedDeviceId)) {
            return ApiResponse::error('device_mismatch', 'The device context does not match this session.', 403);
        }

        $device->forceFill(['last_seen_at' => now()])->save();
        $request->attributes->set('native_device', $device);

        return $next($request);
    }
}
