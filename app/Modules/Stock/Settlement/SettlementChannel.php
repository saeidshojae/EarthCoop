<?php

namespace App\Modules\Stock\Settlement;

final class SettlementChannel
{
    /**
     * Canonical internal settlement. Amounts are integer Gol and must be
     * transferred from Active Bahar only through Najm Bahar.
     */
    public const ACTIVE_BAHAR = 'active_bahar';

    /**
     * External capital channels are payment rails, not EarthCoop balances.
     * They never mint/credit Bahar and are only eligible for EarthCoop's own
     * primary/treasury offering.
     */
    public const EXTERNAL_IRR = 'external_irr';
    public const EXTERNAL_USD = 'external_usd';

    public static function external(): array
    {
        return [self::EXTERNAL_IRR, self::EXTERNAL_USD];
    }

    public static function all(): array
    {
        return [self::ACTIVE_BAHAR, ...self::external()];
    }

    public static function isExternal(string $channel): bool
    {
        return in_array($channel, self::external(), true);
    }
}
