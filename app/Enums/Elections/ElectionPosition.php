<?php

namespace App\Enums\Elections;

enum ElectionPosition: string
{
    case Inspector = 'inspector';
    case Manager = 'manager';

    /**
     * Transitional adapter for the legacy votes.position integer contract.
     *
     * Legacy values are intentionally kept here (and nowhere else) while the
     * election schema is migrated to canonical string-backed domain values.
     */
    public static function fromLegacyVotePosition(int|string $value): self
    {
        return match ((string) $value) {
            '0', self::Inspector->value => self::Inspector,
            '1', self::Manager->value => self::Manager,
            default => throw new \InvalidArgumentException("Unsupported election position [{$value}]."),
        };
    }

    public function legacyVotePosition(): int
    {
        return match ($this) {
            self::Inspector => 0,
            self::Manager => 1,
        };
    }
}
