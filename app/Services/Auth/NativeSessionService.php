<?php

namespace App\Services\Auth;

use App\Models\NativeDevice;
use App\Models\User;
use App\Services\Auth\Data\IssuedNativeSession;
use App\Services\Auth\Data\NativeDeviceData;
use App\Services\Auth\Exceptions\NativeDeviceOwnershipException;
use Illuminate\Support\Str;
use Laravel\Sanctum\PersonalAccessToken;

class NativeSessionService
{
    private const TOKEN_LIFETIME_DAYS = 30;

    public function issue(User $user, NativeDeviceData $deviceData): IssuedNativeSession
    {
        $device = $this->resolveDevice($user, $deviceData);
        $expiresAt = now()->addDays(self::TOKEN_LIFETIME_DAYS);
        $created = $user->createToken(
            'native:' . $device->public_id,
            ['native'],
            $expiresAt,
        );

        $created->accessToken->forceFill([
            'native_device_id' => $device->id,
        ])->save();

        return new IssuedNativeSession(
            device: $device,
            accessToken: $created->accessToken,
            plainTextToken: $created->plainTextToken,
            expiresAt: $expiresAt,
        );
    }

    public function rotate(User $user, NativeDevice $device, PersonalAccessToken $current): IssuedNativeSession
    {
        $session = $this->issue($user, new NativeDeviceData(
            platform: $device->platform,
            appVersion: $device->app_version,
            locale: $device->locale,
            timezone: $device->timezone,
            pushCapable: (bool) $device->push_capable,
            publicId: $device->public_id,
        ));

        $current->delete();

        return $session;
    }

    public function revokeCurrent(User $user, NativeDevice $device, PersonalAccessToken $current): void
    {
        if ((int) $device->user_id !== (int) $user->getAuthIdentifier()) {
            throw new NativeDeviceOwnershipException();
        }

        $current->delete();
        $device->forceFill([
            'revoked_at' => now(),
            'last_seen_at' => now(),
        ])->save();
    }

    private function resolveDevice(User $user, NativeDeviceData $deviceData): NativeDevice
    {
        $device = $deviceData->publicId
            ? NativeDevice::query()->where('public_id', $deviceData->publicId)->first()
            : null;

        if ($device && (int) $device->user_id !== (int) $user->getAuthIdentifier()) {
            throw new NativeDeviceOwnershipException();
        }

        if (! $device) {
            $device = new NativeDevice([
                'public_id' => (string) Str::uuid(),
                'user_id' => $user->getAuthIdentifier(),
            ]);
        }

        $device->forceFill([
            'platform' => $deviceData->platform,
            'app_version' => $deviceData->appVersion,
            'locale' => $deviceData->locale,
            'timezone' => $deviceData->timezone,
            'push_capable' => $deviceData->pushCapable,
            'last_seen_at' => now(),
            'revoked_at' => null,
        ])->save();

        return $device;
    }
}
