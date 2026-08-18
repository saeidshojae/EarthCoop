<?php

namespace App\Modules\Stock\Settlement;

use InvalidArgumentException;
use RuntimeException;

final class SettlementEligibilityPolicy
{
    public const ISSUER_EARTHCOOP = 'earthcoop';
    public const ISSUER_PROJECT = 'project';

    public const MARKET_PRIMARY = 'primary';
    public const MARKET_SECONDARY = 'secondary';

    public const SUPPLY_TREASURY = 'treasury';
    public const SUPPLY_HOLDER = 'holder';

    /**
     * Validate the constitutional boundary between Najm Bahar and external
     * capital. Pricing may be Bahar-denominated regardless of the settlement
     * rail; this policy only governs what may actually settle externally.
     */
    public function assertAllowed(
        string $issuerType,
        string $marketType,
        string $supplySource,
        string $settlementChannel
    ): void {
        $this->assertKnownValues($issuerType, $marketType, $supplySource, $settlementChannel);

        if ($settlementChannel === SettlementChannel::ACTIVE_BAHAR) {
            return;
        }

        if (
            $issuerType !== self::ISSUER_EARTHCOOP
            || $marketType !== self::MARKET_PRIMARY
            || $supplySource !== self::SUPPLY_TREASURY
        ) {
            throw new RuntimeException(
                'External settlement is restricted to EarthCoop primary treasury offerings.'
            );
        }
    }

    public function allowsExternal(
        string $issuerType,
        string $marketType,
        string $supplySource
    ): bool {
        return $issuerType === self::ISSUER_EARTHCOOP
            && $marketType === self::MARKET_PRIMARY
            && $supplySource === self::SUPPLY_TREASURY;
    }

    private function assertKnownValues(
        string $issuerType,
        string $marketType,
        string $supplySource,
        string $settlementChannel
    ): void {
        if (! in_array($issuerType, [self::ISSUER_EARTHCOOP, self::ISSUER_PROJECT], true)) {
            throw new InvalidArgumentException('Unknown stock issuer type.');
        }

        if (! in_array($marketType, [self::MARKET_PRIMARY, self::MARKET_SECONDARY], true)) {
            throw new InvalidArgumentException('Unknown stock market type.');
        }

        if (! in_array($supplySource, [self::SUPPLY_TREASURY, self::SUPPLY_HOLDER], true)) {
            throw new InvalidArgumentException('Unknown stock supply source.');
        }

        if (! in_array($settlementChannel, SettlementChannel::all(), true)) {
            throw new InvalidArgumentException('Unknown stock settlement channel.');
        }
    }
}
