<?php

namespace Tests\Unit\Stock;

use App\Modules\Stock\Settlement\SettlementChannel;
use App\Modules\Stock\Settlement\SettlementEligibilityPolicy;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class SettlementEligibilityPolicyTest extends TestCase
{
    private SettlementEligibilityPolicy $policy;

    protected function setUp(): void
    {
        parent::setUp();
        $this->policy = new SettlementEligibilityPolicy();
    }

    public function test_active_bahar_is_allowed_for_project_primary_market(): void
    {
        $this->policy->assertAllowed(
            SettlementEligibilityPolicy::ISSUER_PROJECT,
            SettlementEligibilityPolicy::MARKET_PRIMARY,
            SettlementEligibilityPolicy::SUPPLY_TREASURY,
            SettlementChannel::ACTIVE_BAHAR,
        );

        $this->addToAssertionCount(1);
    }

    public function test_active_bahar_is_allowed_for_secondary_market(): void
    {
        $this->policy->assertAllowed(
            SettlementEligibilityPolicy::ISSUER_EARTHCOOP,
            SettlementEligibilityPolicy::MARKET_SECONDARY,
            SettlementEligibilityPolicy::SUPPLY_HOLDER,
            SettlementChannel::ACTIVE_BAHAR,
        );

        $this->addToAssertionCount(1);
    }

    public function test_external_irr_is_allowed_only_for_earthcoop_primary_treasury(): void
    {
        $this->policy->assertAllowed(
            SettlementEligibilityPolicy::ISSUER_EARTHCOOP,
            SettlementEligibilityPolicy::MARKET_PRIMARY,
            SettlementEligibilityPolicy::SUPPLY_TREASURY,
            SettlementChannel::EXTERNAL_IRR,
        );

        $this->addToAssertionCount(1);
    }

    public function test_external_usd_is_allowed_only_for_earthcoop_primary_treasury(): void
    {
        $this->policy->assertAllowed(
            SettlementEligibilityPolicy::ISSUER_EARTHCOOP,
            SettlementEligibilityPolicy::MARKET_PRIMARY,
            SettlementEligibilityPolicy::SUPPLY_TREASURY,
            SettlementChannel::EXTERNAL_USD,
        );

        $this->addToAssertionCount(1);
    }

    /**
     * @dataProvider forbiddenExternalSettlements
     */
    public function test_external_settlement_is_rejected_outside_earthcoop_primary_treasury(
        string $issuer,
        string $market,
        string $supply
    ): void {
        $this->expectException(RuntimeException::class);

        $this->policy->assertAllowed(
            $issuer,
            $market,
            $supply,
            SettlementChannel::EXTERNAL_IRR,
        );
    }

    public static function forbiddenExternalSettlements(): array
    {
        return [
            'project primary' => [
                SettlementEligibilityPolicy::ISSUER_PROJECT,
                SettlementEligibilityPolicy::MARKET_PRIMARY,
                SettlementEligibilityPolicy::SUPPLY_TREASURY,
            ],
            'earthcoop secondary' => [
                SettlementEligibilityPolicy::ISSUER_EARTHCOOP,
                SettlementEligibilityPolicy::MARKET_SECONDARY,
                SettlementEligibilityPolicy::SUPPLY_HOLDER,
            ],
            'project secondary' => [
                SettlementEligibilityPolicy::ISSUER_PROJECT,
                SettlementEligibilityPolicy::MARKET_SECONDARY,
                SettlementEligibilityPolicy::SUPPLY_HOLDER,
            ],
            'earthcoop primary holder supply' => [
                SettlementEligibilityPolicy::ISSUER_EARTHCOOP,
                SettlementEligibilityPolicy::MARKET_PRIMARY,
                SettlementEligibilityPolicy::SUPPLY_HOLDER,
            ],
        ];
    }

    public function test_unknown_channel_fails_closed(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->policy->assertAllowed(
            SettlementEligibilityPolicy::ISSUER_EARTHCOOP,
            SettlementEligibilityPolicy::MARKET_PRIMARY,
            SettlementEligibilityPolicy::SUPPLY_TREASURY,
            'stock_wallet',
        );
    }
}
