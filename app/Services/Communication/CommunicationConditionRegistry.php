<?php

namespace App\Services\Communication;

use App\Models\User;
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

    /** @param array<string,mixed> $context */
    public function evaluate(string $key, User $user, array $context = []): bool
    {
        $this->get($key);

        return match ($key) {
            'user.email_verified' => $user->email_verified_at !== null,
            // Registration completion becomes event-driven in Task 6. Until then,
            // only an explicit trusted event context can assert this condition.
            'user.registration_complete' => ($context['registration_completed'] ?? false) === true,
            default => throw new InvalidArgumentException("No evaluator registered for communication condition: {$key}"),
        };
    }
}
