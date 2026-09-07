<?php

namespace Tests\Unit\Stock;

use App\Modules\NajmBahar\Models\Account;
use App\Modules\NajmBahar\Services\ActiveBaharReservationService;
use App\Modules\Stock\Settlement\NajmBaharSettlementGateway;
use App\Modules\Stock\Settlement\SettlementChannel;
use App\Modules\Stock\Settlement\SettlementReceipt;
use App\Modules\Stock\Settlement\SettlementRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NajmBaharSettlementGatewayTest extends TestCase
{
    use RefreshDatabase;

    public function test_gateway_reserve_settle_and_refund_use_active_bahar_only(): void
    {
        $payer=$this->account('1000000001',1000); $payee=$this->account('0000000001',0,'system');
        $gateway=new NajmBaharSettlementGateway(app(ActiveBaharReservationService::class));

        $reserve=$gateway->reserve(new SettlementRequest(SettlementChannel::ACTIVE_BAHAR,400,'bid:7:reserve','auction_bid',7,$payer->account_number,null));
        $this->assertSame(SettlementReceipt::RESERVED,$reserve->status);

        $settle=$gateway->settle(new SettlementRequest(SettlementChannel::ACTIVE_BAHAR,400,'bid:7:settle','auction_bid',7,$payer->account_number,$payee->account_number,['reservation_key'=>'bid:7:reserve']));
        $this->assertSame(SettlementReceipt::SETTLED,$settle->status);

        $refund=$gateway->refund(new SettlementRequest(SettlementChannel::ACTIVE_BAHAR,100,'bid:7:refund:1','auction_bid',7,$payer->account_number,$payee->account_number,['reservation_key'=>'bid:7:reserve']));
        $this->assertSame(SettlementReceipt::REFUNDED,$refund->status);
        $this->assertSame(700,(int)$payer->fresh()->balance_active);
        $this->assertSame(300,(int)$payee->fresh()->balance_active);
    }

    private function account(string $number,int $active,string $type='user'): Account
    {
        return Account::create(['account_number'=>$number,'name'=>$number,'type'=>$type,'balance'=>$active,'balance_active'=>$active,'balance_faded'=>0,'status'=>1]);
    }
}
