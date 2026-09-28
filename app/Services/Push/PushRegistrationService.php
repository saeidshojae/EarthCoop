<?php

namespace App\Services\Push;

use App\Models\NativeDevice;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Validation\ValidationException;

class PushRegistrationService
{
    public function register(NativeDevice $device, string $provider, string $token): NativeDevice
    {
        $provider = strtolower(trim($provider));
        $token = trim($token);

        if (! in_array($provider, ['fcm', 'apns'], true)) {
            throw ValidationException::withMessages(['provider' => ['Unsupported push provider.']]);
        }

        if ($token === '') {
            throw ValidationException::withMessages(['token' => ['Push token is required.']]);
        }

        $hash = hash('sha256', $token);

        $conflict = NativeDevice::query()
            ->where('push_provider', $provider)
            ->where('push_token_hash', $hash)
            ->whereKeyNot($device->getKey())
            ->whereNull('push_disabled_at')
            ->exists();

        if ($conflict) {
            throw new PushTokenInUseException();
        }

        try {
            $device->forceFill([
                'push_capable' => true,
                'push_provider' => $provider,
                'push_token' => Crypt::encryptString($token),
                'push_token_hash' => $hash,
                'push_token_updated_at' => now(),
                'push_enabled_at' => now(),
                'push_disabled_at' => null,
                'last_push_failure_code' => null,
            ])->save();
        } catch (QueryException $e) {
            if ($this->isUniqueViolation($e)) {
                throw new PushTokenInUseException(previous: $e);
            }

            throw $e;
        }

        return $device->fresh();
    }

    public function disable(NativeDevice $device): NativeDevice
    {
        $device->forceFill([
            'push_capable' => false,
            'push_disabled_at' => now(),
        ])->save();

        return $device->fresh();
    }

    private function isUniqueViolation(QueryException $e): bool
    {
        return in_array((string) $e->getCode(), ['23000', '23505'], true);
    }
}

class PushTokenInUseException extends \RuntimeException
{
    public function __construct(string $message = 'Push token is already active on another device.', ?\Throwable $previous = null)
    {
        parent::__construct($message, 0, $previous);
    }
}
