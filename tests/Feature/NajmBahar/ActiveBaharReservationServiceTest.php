<?php

namespace Tests\Feature\NajmBahar;

use App\Modules\NajmBahar\Models\Account;
use App\Modules\NajmBahar\Models\ActiveBaharReservation;
use App\Modules\NajmBahar\Services\ActiveBaharReservationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ActiveBaharReservationServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_reservation_reduces_spendable_without_changing_money_supply(): void
    {
        $payer=$this->account('1000000001',1000); $service=app(ActiveBaharReservationService::class);
        $r=$service->reserve($payer->account_number,400,'reserve:1','auction_bid',1);
        $this->assertSame(600,$service->availableActive($payer->fresh()));
        $this->assertSame(1000,(int)$payer->fresh()->balance_active);
        $this->assertSame(ActiveBaharReservation::RESERVED,$r->status);
    }

    public function test_reserve_is_idempotent_and_conflicting_reuse_fails(): void
    {
        $payer=$this->account('1000000001',1000); $service=app(ActiveBaharReservationService::class);
        $first=$service->reserve($payer->account_number,400,'reserve:1','auction_bid',1);
        $second=$service->reserve($payer->account_number,400,'reserve:1','auction_bid',1);
        $this->assertSame($first->id,$second->id);
        $this->expectException(\RuntimeException::class);
        $service->reserve($payer->account_number,401,'reserve:1','auction_bid',1);
    }

    public function test_release_restores_spendable_without_balance_mutation(): void
    {
        $payer=$this->account('1000000001',1000); $service=app(ActiveBaharReservationService::class);
        $service->reserve($payer->account_number,400,'reserve:1','auction_bid',1);
        $service->release('reserve:1','release:1');
        $this->assertSame(1000,$service->availableActive($payer->fresh()));
        $this->assertSame(1000,(int)$payer->fresh()->balance_active);
    }

    public function test_settlement_consumes_reserved_active_and_credits_destination_with_double_entry_ledger(): void
    {
        $payer=$this->account('1000000001',1000); $payee=$this->account('0000000001',0,'system'); $service=app(ActiveBaharReservationService::class);
        $service->reserve($payer->account_number,400,'reserve:1','auction_bid',1);
        $r=$service->settle('reserve:1',$payee->account_number,'settle:1');
        $this->assertSame(600,(int)$payer->fresh()->balance_active);
        $this->assertSame(400,(int)$payee->fresh()->balance_active);
        $this->assertSame(ActiveBaharReservation::SETTLED,$r->status);
        $this->assertDatabaseHas('najm_ledger_entries',['account_id'=>$payer->id,'amount'=>-400,'entry_type'=>'debit']);
        $this->assertDatabaseHas('najm_ledger_entries',['account_id'=>$payee->id,'amount'=>400,'entry_type'=>'credit']);
    }

    public function test_refund_is_idempotent_and_cannot_exceed_settled_amount(): void
    {
        $payer=$this->account('1000000001',1000); $payee=$this->account('0000000001',0,'system'); $service=app(ActiveBaharReservationService::class);
        $service->reserve($payer->account_number,400,'reserve:1','auction_bid',1); $service->settle('reserve:1',$payee->account_number,'settle:1');
        $service->refund('reserve:1',150,'refund:1'); $service->refund('reserve:1',150,'refund:1');
        $this->assertSame(750,(int)$payer->fresh()->balance_active); $this->assertSame(250,(int)$payee->fresh()->balance_active);
        $this->expectException(\RuntimeException::class); $service->refund('reserve:1',251,'refund:2');
    }

    private function account(string $number,int $active,string $type='user'): Account
    {
        return Account::create(['account_number'=>$number,'name'=>$number,'type'=>$type,'balance'=>$active,'balance_active'=>$active,'balance_faded'=>0,'status'=>1]);
    }
}
