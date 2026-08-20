<?php

namespace Tests\Feature\Stock;

use App\Modules\NajmBahar\Models\Account;
use App\Modules\Stock\Models\Auction;
use App\Modules\Stock\Models\Stock;
use App\Modules\Stock\Services\StockCanonicalReadinessService;
use App\Modules\Stock\Settlement\SettlementChannel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StockCanonicalReadinessServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_primary_active_bahar_is_ready_when_required_capital_account_exists(): void
    {
        $capital=Account::create(['account_number'=>'0000000001','name'=>'capital','type'=>'central','balance'=>0,'balance_active'=>0,'balance_faded'=>0,'status'=>1]);
        config(['stock.earthcoop_capital_account_number'=>$capital->account_number]);
        $this->canonicalAuction(SettlementChannel::ACTIVE_BAHAR,'primary','treasury');

        $audit=app(StockCanonicalReadinessService::class)->audit();
        $this->assertTrue($audit['ready']);
        $this->assertSame(0,$audit['summary']['blockers']);
    }

    public function test_external_and_secondary_cutovers_fail_closed_by_default(): void
    {
        $capital=Account::create(['account_number'=>'0000000001','name'=>'capital','type'=>'central','balance'=>0,'balance_active'=>0,'balance_faded'=>0,'status'=>1]);
        config([
            'stock.earthcoop_capital_account_number'=>$capital->account_number,
            'stock.external_capital_enabled'=>false,
            'stock.secondary_market_enabled'=>false,
        ]);
        $this->canonicalAuction(SettlementChannel::EXTERNAL_USD,'primary','treasury');
        $this->canonicalAuction(SettlementChannel::ACTIVE_BAHAR,'secondary','holder');

        $audit=app(StockCanonicalReadinessService::class)->audit();
        $codes=collect($audit['blockers'])->pluck('code')->all();
        $this->assertFalse($audit['ready']);
        $this->assertContains('external_capital_not_configured',$codes);
        $this->assertContains('secondary_market_not_cut_over',$codes);
    }

    private function canonicalAuction(string $channel,string $market,string $supply): Auction
    {
        $stock=Stock::create(['issuer_type'=>'earthcoop','startup_valuation'=>1000,'startup_valuation_gol'=>1000,'total_shares'=>100,'available_shares'=>100,'base_share_price'=>1,'base_share_price_gol'=>10]);
        return Auction::create(['stock_id'=>$stock->id,'market_type'=>$market,'supply_source'=>$supply,'settlement_channel'=>$channel,'quote_unit'=>'gol','shares_count'=>10,'base_price'=>1,'base_price_gol'=>10,'start_time'=>now()->subMinute(),'ends_at'=>now()->addHour(),'status'=>'running','type'=>'pay_as_bid','lot_size'=>10]);
    }
}
