<?php

namespace App\Services\Communication;

use InvalidArgumentException;

class CommunicationConditionRegistry
{
    /** @var array<string,array<string,mixed>> */
    private array $definitions = [
        'user.registration_complete' => [
            'key' => 'user.registration_complete',
            'cost' => 'low',
        ],
        'user.email_verified' => [
            'key' => 'user.email_verified',
            'cost' => 'low',
        ],
    ];

    /** @return array<string,mixed> */
    public function get(string $key): array
    {
        if (! isset($this->definitions[$key])) {
            throw new InvalidArgumentException("Unregistered communication condition: {$key}");
        }

        return $this->definitions[$key];
    }

    public function has(string $key): bool
    {
        return isset($this->definitions[$key]);
    }
}
