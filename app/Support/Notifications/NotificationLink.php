<?php

namespace App\Support\Notifications;

final readonly class NotificationLink
{
    public function __construct(
        public int $version,
        public string $route,
        public array $params = [],
        public ?string $fallbackUrl = null,
    ) {
    }

    public function toArray(): array
    {
        return [
            'version' => $this->version,
            'route' => $this->route,
            'params' => $this->params,
            'fallback_url' => $this->fallbackUrl,
        ];
    }
}
