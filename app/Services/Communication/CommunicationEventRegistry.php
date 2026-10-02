<?php

namespace App\Services\Communication;

use InvalidArgumentException;

class CommunicationEventRegistry
{
    /** @var array<string,array<string,mixed>> */
    private array $definitions = [
        'registration.completed' => [
            'key' => 'registration.completed',
            'required_payload' => ['user_id', 'completed_at', 'locale'],
        ],
    ];

    /** @return array<string,mixed> */
    public function get(string $key): array
    {
        if (! isset($this->definitions[$key])) {
            throw new InvalidArgumentException("Unregistered communication event: {$key}");
        }

        return $this->definitions[$key];
    }

    public function has(string $key): bool
    {
        return isset($this->definitions[$key]);
    }
}
