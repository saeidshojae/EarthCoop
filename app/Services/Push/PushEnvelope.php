<?php

namespace App\Services\Push;

final readonly class PushEnvelope
{
    public function __construct(
        public string $title,
        public string $body,
        public string $type,
        public ?array $link,
        public array $context = [],
    ) {
    }

    public function toArray(): array
    {
        return [
            'title' => $this->title,
            'body' => $this->body,
            'type' => $this->type,
            'link' => $this->link,
            'context' => $this->context,
        ];
    }
}
