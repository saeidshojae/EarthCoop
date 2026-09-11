<?php

namespace App\Services\Deployment;

use InvalidArgumentException;

class DeploymentConsoleService
{
    private const OPERATIONS = [
        'migration_status' => [
            'command' => 'migrate:status',
            'arguments' => [],
            'write' => false,
            'confirmation' => null,
        ],
        'reference_dry_run' => [
            'command' => 'location:reference-import',
            'arguments' => [
                'country' => 'IR',
                '--dataset-version' => 'v1',
                '--dry-run' => true,
            ],
            'write' => false,
            'confirmation' => null,
        ],
        'readiness' => [
            'command' => 'location-governance:readiness',
            'arguments' => [],
            'write' => false,
            'confirmation' => null,
        ],
        'flag_status' => [
            'command' => null,
            'arguments' => [],
            'write' => false,
            'confirmation' => null,
        ],
        'migrate' => [
            'command' => 'migrate',
            'arguments' => ['--force' => true],
            'write' => true,
            'confirmation' => 'MIGRATE',
        ],
        'bootstrap' => [
            'command' => 'db:seed',
            'arguments' => [
                '--class' => 'LocationGovernanceBootstrapSeeder',
                '--force' => true,
            ],
            'write' => true,
            'confirmation' => 'BOOTSTRAP',
        ],
        'reference_apply' => [
            'command' => 'location:reference-import',
            'arguments' => [
                'country' => 'IR',
                '--dataset-version' => 'v1',
                '--apply' => true,
            ],
            'write' => true,
            'confirmation' => 'APPLY-IR',
        ],
    ];

    public function operations(): array
    {
        return self::OPERATIONS;
    }

    public function isEnabled(): bool
    {
        return (bool) config('deployment-console.enabled', false);
    }

    public function secretMatches(string $secret): bool
    {
        $configured = config('deployment-console.secret');

        return is_string($configured)
            && $configured !== ''
            && $secret !== ''
            && hash_equals($configured, $secret);
    }

    public function confirmationFor(string $operation): ?string
    {
        if (! array_key_exists($operation, self::OPERATIONS)) {
            throw new InvalidArgumentException('Unknown deployment console operation.');
        }

        return self::OPERATIONS[$operation]['confirmation'];
    }
}
