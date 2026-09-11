<?php

namespace App\Data\LocationGovernance;

use InvalidArgumentException;

final class GovernanceCapabilities
{
    private const GROUP_CREATION_MODES = [
        'automatic',
        'threshold',
        'on_demand',
        'disabled',
    ];

    public function __construct(
        private readonly array $values,
    ) {
        $mode = $this->values['group_creation_mode'] ?? 'disabled';

        if (! in_array($mode, self::GROUP_CREATION_MODES, true)) {
            throw new InvalidArgumentException("Unsupported governance group creation mode [{$mode}].");
        }
    }

    public function enabled(string $capability): bool
    {
        return (bool) ($this->values[$capability] ?? false);
    }

    public function groupCreationMode(): string
    {
        return (string) ($this->values['group_creation_mode'] ?? 'disabled');
    }

    public function all(): array
    {
        return $this->values;
    }
}
