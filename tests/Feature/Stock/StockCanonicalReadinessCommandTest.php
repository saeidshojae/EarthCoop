<?php

namespace Tests\Feature\Stock;

use App\Modules\NajmBahar\Models\Account;
use App\Modules\Stock\Models\Auction;
use App\Modules\Stock\Models\Stock;
use App\Modules\Stock\Settlement\SettlementChannel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StockCanonicalReadinessCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_fail_on_blocker_returns_failure(): void
    {
        $stock=Stock::create([
            'issuer_type'=>'earthcoop','startup_valuation'=>1000,'startup_valuation_gol'=>1000,
            'total_shares'=>100,'available_shares'=>100,'base_share_price'=>1,'base_share_price_gol'=>10,
        ]);
        Auction::create([
            'stock_id'=>$stock->id,'market_type'=>'primary','supply_source'=>'treasury',
            'settlement_channel'=>SettlementChannel::ACTIVE_BAHAR,'quote_unit'=>'gol',
            'shares_count'=>10,'base_price'=>0,'base_price_gol'=>10,
            'start_time'=>now()->subMinute(),'ends_at'=>now()->addHour(),
            'status'=>'running','type'=>'single_winner','lot_size'=>10,
        ]);
        config(['stock.earthcoop_capital_account_number'=>'']);

        $this->artisan('stock:canonical-readiness --fail-on-blocker')
            ->expectsOutputToContain('Ready: NO')
            ->assertExitCode(1);
    }

    public function test_ready_scope_returns_success(): void
    {
        $capital=Account::create([
            'account_number'=>'0000000001','name'=>'capital','type'=>'central',
            'balance'=>0,'balance_active'=>0,'balance_faded'=>0,'status'=>1,
        ]);
        config(['stock.earthcoop_capital_account_number'=>$capital->account_number]);
        $stock=Stock::create([
            'issuer_type'=>'earthcoop','startup_valuation'=>1000,'startup_valuation_gol'=>1000,
            'total_shares'=>100,'available_shares'=>100,'base_share_price'=>1,'base_share_price_gol'=>10,
        ]);
        Auction::create([
            'stock_id'=>$stock->id,'market_type'=>'primary','supply_source'=>'treasury',
            'settlement_channel'=>SettlementChannel::ACTIVE_BAHAR,'quote_unit'=>'gol',
            'shares_count'=>10,'base_price'=>0,'base_price_gol'=>10,
            'start_time'=>now()->subMinute(),'ends_at'=>now()->addHour(),
            'status'=>'running','type'=>'single_winner','lot_size'=>10,
        ]);

        $this->artisan('stock:canonical-readiness --fail-on-blocker')
            ->expectsOutputToContain('Ready: YES')
            ->assertExitCode(0);
    }
}
