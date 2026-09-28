<?php

namespace App\Services\Push;

use App\Models\NotificationSetting;
use App\Models\User;

class PushNotificationDispatcher
{
    public function __construct(
        private readonly PushEligibilityResolver $eligibility,
        private readonly PushDeliveryGateway $gateway,
    ) {
    }

    public function dispatch(User $user, array $notificationPayload): void
    {
        $settings = NotificationSetting::forUser((int) $user->id);
        if (! (bool) $settings->push_notifications) {
            return;
        }

        $envelope = new PushEnvelope(
            title: (string) ($notificationPayload['title'] ?? 'EarthCoop'),
            body: (string) ($notificationPayload['message'] ?? ''),
            type: (string) ($notificationPayload['type'] ?? 'info'),
            link: isset($notificationPayload['link']) && is_array($notificationPayload['link'])
                ? $notificationPayload['link']
                : null,
            context: array_filter([
                'notification_id' => $notificationPayload['notification_id'] ?? null,
                'fallback_url' => $notificationPayload['url'] ?? null,
            ], static fn ($value) => $value !== null),
        );

        foreach ($this->eligibility->eligibleFor($user) as $device) {
            try {
                $result = $this->gateway->send($device, $envelope);
            } catch (\Throwable) {
                $result = PushDeliveryResult::temporaryFailure('provider_exception');
            }

            if ($result->delivered()) {
                $device->forceFill([
                    'last_push_success_at' => now(),
                    'last_push_failure_at' => null,
                    'last_push_failure_code' => null,
                ])->save();
                continue;
            }

            $updates = [
                'last_push_failure_at' => now(),
                'last_push_failure_code' => $result->code ?: $result->status,
            ];

            if ($result->status === PushDeliveryResult::INVALID_TOKEN) {
                $updates['push_capable'] = false;
                $updates['push_disabled_at'] = now();
            }

            $device->forceFill($updates)->save();
        }
    }
}
