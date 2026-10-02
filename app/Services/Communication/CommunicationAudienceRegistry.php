<?php

namespace App\Services\Communication;

use InvalidArgumentException;

class CommunicationAudienceRegistry
{
    /** @var array<string,array<string,mixed>> */
    private array $definitions = [
        'event.user' => ['key' => 'event.user'],
        'specific.user' => ['key' => 'specific.user'],
        'role.member' => ['key' => 'role.member'],
        'role.manager' => ['key' => 'role.manager'],
        'role.inspector' => ['key' => 'role.inspector'],
    ];

    /** @return array<string,mixed> */
    public function get(string $key): array
    {
        if (! isset($this->definitions[$key])) {
            throw new InvalidArgumentException("Unregistered communication audience: {$key}");
        }

        return $this->definitions[$key];
    }

    public function has(string $key): bool
    {
        return isset($this->definitions[$key]);
    }
}
