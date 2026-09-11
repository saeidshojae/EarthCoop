<?php

namespace App\Services\Deployment;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
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

    public function flags(): array
    {
        return [
            'runtime_enabled' => (bool) config('location-governance.runtime_enabled', false),
            'registration_enabled' => (bool) config('location-governance.registration_enabled', false),
            'groups_enabled' => (bool) config('location-governance.groups_enabled', false),
            'elections_enabled' => (bool) config('location-governance.elections_enabled', false),
            'projects_enabled' => (bool) config('location-governance.projects_enabled', false),
        ];
    }

    public function run(string $operation, int $userId, ?string $ip = null): array
    {
        if (! array_key_exists($operation, self::OPERATIONS)) {
            throw new InvalidArgumentException('Unknown deployment console operation.');
        }

        $definition = self::OPERATIONS[$operation];

        if ($operation === 'flag_status') {
            $output = collect($this->flags())
                ->map(fn (bool $enabled, string $flag): string => $flag.'='.($enabled ? 'true' : 'false'))
                ->implode(PHP_EOL);
            $exitCode = 0;
        } else {
            $exitCode = Artisan::call($definition['command'], $definition['arguments']);
            $output = trim(Artisan::output());
        }

        $result = [
            'operation' => $operation,
            'exit_code' => $exitCode,
            'success' => $exitCode === 0,
            'output' => trim($output),
        ];

        Log::channel('deployment-console')->info('deployment_console_operation', [
            'user_id' => $userId,
            'operation' => $operation,
            'write' => (bool) $definition['write'],
            'exit_code' => $exitCode,
            'success' => $result['success'],
            'ip' => $ip,
        ]);

        return $result;
    }
}
