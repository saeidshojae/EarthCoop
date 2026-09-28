<?php

namespace App\Http\Controllers\API\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\API\V1\DevicePushRequest;
use App\Http\Support\Api\V1\ApiResponse;
use App\Models\NativeDevice;
use App\Services\Push\PushRegistrationService;
use App\Services\Push\PushTokenInUseException;
use Illuminate\Http\Request;

class DevicePushController extends Controller
{
    public function update(DevicePushRequest $request, string $device, PushRegistrationService $service)
    {
        $current = $this->currentDevice($request, $device);

        try {
            $current = $service->register(
                $current,
                (string) $request->validated('provider'),
                (string) $request->validated('token'),
            );
        } catch (PushTokenInUseException $e) {
            return ApiResponse::error('push_token_in_use', $e->getMessage(), 409);
        }

        return ApiResponse::success($this->payload($current));
    }

    public function destroy(Request $request, string $device, PushRegistrationService $service)
    {
        $current = $service->disable($this->currentDevice($request, $device));

        return ApiResponse::success($this->payload($current));
    }

    private function currentDevice(Request $request, string $publicId): NativeDevice
    {
        $current = $request->attributes->get('native_device');

        if (! $current instanceof NativeDevice || ! hash_equals((string) $current->public_id, $publicId)) {
            abort(404);
        }

        return $current;
    }

    private function payload(NativeDevice $device): array
    {
        return [
            'device_id' => (string) $device->public_id,
            'provider' => $device->push_provider,
            'push_enabled' => $device->push_disabled_at === null && $device->push_enabled_at !== null,
            'push_token_updated_at' => $device->push_token_updated_at?->toISOString(),
        ];
    }
}
