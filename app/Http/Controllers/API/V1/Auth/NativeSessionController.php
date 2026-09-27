<?php

namespace App\Http\Controllers\API\V1\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\API\V1\Auth\NativeLoginRequest;
use App\Http\Resources\API\V1\UserResource;
use App\Http\Support\Api\V1\ApiResponse;
use App\Models\NativeDevice;
use App\Models\User;
use App\Services\Auth\Data\IssuedNativeSession;
use App\Services\Auth\Data\NativeDeviceData;
use App\Services\Auth\Exceptions\NativeDeviceOwnershipException;
use App\Services\Auth\NativeSessionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\PersonalAccessToken;

class NativeSessionController extends Controller
{
    public function store(NativeLoginRequest $request, NativeSessionService $sessions)
    {
        $user = User::query()->where('email', $request->string('email')->toString())->first();

        if (! $user || $user->isSystemIdentity() || ! Hash::check((string) $request->input('password'), (string) $user->password)) {
            return ApiResponse::error('invalid_credentials', 'The supplied credentials are invalid.', 401);
        }

        try {
            $session = $sessions->issue($user, NativeDeviceData::fromArray($request->validated()));
        } catch (NativeDeviceOwnershipException) {
            return ApiResponse::error('device_not_owned', 'The supplied device belongs to another account.', 403);
        }

        $user->forceFill([
            'last_login_ip' => $request->ip(),
            'last_login_at' => now(),
        ])->save();

        return ApiResponse::success($this->sessionPayload($request, $user, $session), 201);
    }

    public function show(Request $request)
    {
        /** @var User $user */
        $user = $request->user();
        /** @var NativeDevice $device */
        $device = $request->attributes->get('native_device');
        /** @var PersonalAccessToken $token */
        $token = $user->currentAccessToken();

        return ApiResponse::success([
            'token_type' => 'Bearer',
            'expires_at' => $token->expires_at?->utc()?->toIso8601ZuluString(),
            'user' => (new UserResource($user))->resolve($request),
            'device' => $this->devicePayload($device),
        ]);
    }

    public function rotate(Request $request, NativeSessionService $sessions)
    {
        /** @var User $user */
        $user = $request->user();
        /** @var NativeDevice $device */
        $device = $request->attributes->get('native_device');
        /** @var PersonalAccessToken $token */
        $token = $user->currentAccessToken();

        $session = $sessions->rotate($user, $device, $token);

        return ApiResponse::success($this->sessionPayload($request, $user, $session));
    }

    public function destroy(Request $request, NativeSessionService $sessions)
    {
        /** @var User $user */
        $user = $request->user();
        /** @var NativeDevice $device */
        $device = $request->attributes->get('native_device');
        /** @var PersonalAccessToken $token */
        $token = $user->currentAccessToken();

        $sessions->revokeCurrent($user, $device, $token);

        return response()->noContent();
    }

    private function sessionPayload(Request $request, User $user, IssuedNativeSession $session): array
    {
        return [
            'token' => $session->plainTextToken,
            'token_type' => 'Bearer',
            'expires_at' => $session->expiresAt->utc()->toIso8601ZuluString(),
            'user' => (new UserResource($user))->resolve($request),
            'device' => $this->devicePayload($session->device),
        ];
    }

    private function devicePayload(NativeDevice $device): array
    {
        return [
            'id' => $device->public_id,
            'platform' => $device->platform,
            'app_version' => $device->app_version,
            'locale' => $device->locale,
            'timezone' => $device->timezone,
            'push_capable' => (bool) $device->push_capable,
            'last_seen_at' => $device->last_seen_at?->utc()?->toIso8601ZuluString(),
        ];
    }
}
