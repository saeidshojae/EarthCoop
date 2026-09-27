<?php

namespace App\Services\Auth\Data;

final class NativeDeviceData
{
    public function __construct(
        public readonly string $platform,
        public readonly string $appVersion,
        public readonly string $locale,
        public readonly ?string $timezone,
        public readonly bool $pushCapable,
        public readonly ?string $publicId = null,
    ) {
    }

    public static function fromArray(array $data): self
    {
        return new self(
            platform: (string) $data['platform'],
            appVersion: (string) $data['app_version'],
            locale: (string) $data['locale'],
            timezone: isset($data['timezone']) ? (string) $data['timezone'] : null,
            pushCapable: (bool) ($data['push_capable'] ?? false),
            publicId: isset($data['device_id']) ? (string) $data['device_id'] : null,
        );
    }
}
