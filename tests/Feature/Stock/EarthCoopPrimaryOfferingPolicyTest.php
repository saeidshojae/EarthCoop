<?php

namespace Tests\Feature\Stock;

use App\Models\User;
use App\Modules\Stock\Models\Auction;
use App\Modules\Stock\Models\Stock;
use App\Modules\Stock\Models\StockSettlementAllocation;
use App\Modules\Stock\Services\EarthCoopPrimaryOfferingPolicy;
use App\Modules\Stock\Settlement\SettlementChannel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class EarthCoopPrimaryOfferingPolicyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('stock.primary_offering.max_allocation_bps', 1000);
        config()->set('stock.primary_offering.policy_version', 'earthcoop-primary-v1');
        config()->set('stock.primary_offering.disclosure_version', 'earthcoop-primary-disclosure-v1');
    }

    public function test_valid_primary_treasury_offering_returns_versioned_evidence(): void
    {
        $stock = $this->stock(1000, 1000);
        $auction = $this->auction($stock, 100);

        $evidence = app(EarthCoopPrimaryOfferingPolicy::class)->assertEligible($auction);

        $this->assertSame(1000, $evidence['max_allocation_bps']);
        $this->assertSame(100, $evidence['max_primary_shares']);
        $this->assertSame('earthcoop-primary-v1', $evidence['policy_version']);
        $this->assertSame('earthcoop-primary-disclosure-v1', $evidence['disclosure_version']);
    }

    public function test_offering_above_ten_percent_cap_fails_closed(): void
    {
        $stock = $this->stock(1000, 1000);
        $auction = $this->auction($stock, 101);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('primary allocation cap');
        app(EarthCoopPrimaryOfferingPolicy::class)->assertEligible($auction);
    }

    public function test_open_offerings_cannot_oversubscribe_cap(): void
    {
        $stock = $this->stock(1000, 1000);
        $this->auction($stock, 90, 'scheduled');
        $candidate = $this->auction($stock, 20, 'running');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('oversubscribe');
        app(EarthCoopPrimaryOfferingPolicy::class)->assertEligible($candidate);
    }

    public function test_canonical_settled_primary_allocations_consume_cap(): void
    {
        $user = User::factory()->create();
        $stock = $this->stock(1000, 1000);
        $settledAuction = $this->auction($stock, 90, 'settled');
        StockSettlementAllocation::create([
            'allocation_key' => 'settled-cap-1',
            'auction_id' => $settledAuction->id,
            'user_id' => $user->id,
            'stock_id' => $stock->id,
            'settlement_channel' => SettlementChannel::EXTERNAL_USD,
            'quantity' => 90,
            'price_gol' => 10,
            'total_gol' => 900,
            'state' => StockSettlementAllocation::SETTLED,
            'money_state' => 'confirmed_external',
            'asset_state' => 'settled',
        ]);
        $candidate = $this->auction($stock, 20, 'running');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('oversubscribe');
        app(EarthCoopPrimaryOfferingPolicy::class)->assertEligible($candidate);
    }

    public function test_offering_cannot_exceed_real_treasury_available_shares(): void
    {
        $stock = $this->stock(1000, 10);
        $auction = $this->auction($stock, 20);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('treasury');
        app(EarthCoopPrimaryOfferingPolicy::class)->assertEligible($auction);
    }

    public function test_non_earthcoop_or_non_primary_or_non_treasury_offering_is_rejected(): void
    {
        $stock = $this->stock(1000, 1000, 'project');
        $auction = $this->auction($stock, 10);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('EarthCoop primary treasury');
        app(EarthCoopPrimaryOfferingPolicy::class)->assertEligible($auction);
    }

    private function stock(int $totalShares, int $availableShares, string $issuer = 'earthcoop'): Stock
    {
        return Stock::create([
            'issuer_type' => $issuer,
            'startup_valuation' => 1000,
            'startup_valuation_gol' => 100000,
            'total_shares' => $totalShares,
            'available_shares' => $availableShares,
            'base_share_price' => 1,
            'base_share_price_gol' => 100,
        ]);
    }

    private function auction(Stock $stock, int $shares, string $status = 'running'): Auction
    {
        return Auction::create([
            'stock_id' => $stock->id,
            'market_type' => 'primary',
            'supply_source' => 'treasury',
            'settlement_channel' => SettlementChannel::EXTERNAL_USD,
            'quote_unit' => 'gol',
            'shares_count' => $shares,
            'base_price' => 1,
            'base_price_gol' => 10,
            'start_time' => now(),
            'ends_at' => now()->addDay(),
            'status' => $status,
            'type' => 'uniform_price',
            'lot_size' => 1,
        ]);
    }
}
