<?php

namespace Tests\Feature\Stock;

use App\Models\User;
use App\Modules\Stock\Models\Holding;
use App\Modules\Stock\Models\HoldingReservation;
use App\Modules\Stock\Models\Stock;
use App\Modules\Stock\Services\HoldingReservationService;
use App\Modules\Stock\Services\SecondaryListingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SecondaryListingServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_listing_reserves_seller_shares_and_is_idempotent(): void
    {
        config(['stock.secondary_market_enabled'=>true]);
        [$seller,$stock,$holding]=$this->holding(10);
        $service=app(SecondaryListingService::class);

        $first=$service->create($seller->id,$stock->id,6,25,20,40,'uniform_price',now()->addHour(),'listing:1');
        $second=$service->create($seller->id,$stock->id,6,25,20,40,'uniform_price',now()->addHour(),'listing:1');

        $this->assertSame($first->id,$second->id);
        $this->assertSame($seller->id,(int)$first->seller_user_id);
        $this->assertSame('secondary',(string)$first->market_type);
        $this->assertSame('holder',(string)$first->supply_source);
        $this->assertSame('active_bahar',(string)$first->settlement_channel);
        $this->assertDatabaseHas('stock_holding_reservations',[
            'reservation_key'=>'secondary:listing:listing:1:shares',
            'holding_id'=>$holding->id,
            'quantity'=>6,
            'status'=>HoldingReservation::RESERVED,
        ]);
        $this->assertSame(4,app(HoldingReservationService::class)->availableQuantity($holding->fresh()));
    }

    public function test_double_sell_is_blocked_by_available_holding_reservation(): void
    {
        config(['stock.secondary_market_enabled'=>true]);
        [$seller,$stock]=$this->holding(10);
        $service=app(SecondaryListingService::class);
        $service->create($seller->id,$stock->id,7,25,null,null,'pay_as_bid',now()->addHour(),'listing:1');

        $this->expectException(\RuntimeException::class);
        $service->create($seller->id,$stock->id,4,25,null,null,'pay_as_bid',now()->addHour(),'listing:2');
    }

    public function test_cancel_releases_seller_supply_and_is_idempotent(): void
    {
        config(['stock.secondary_market_enabled'=>true]);
        [$seller,$stock,$holding]=$this->holding(10);
        $service=app(SecondaryListingService::class);
        $auction=$service->create($seller->id,$stock->id,6,25,null,null,'single_winner',now()->addHour(),'listing:1');

        $cancelled=$service->cancel($auction,$seller->id,'cancel:1');
        $again=$service->cancel($cancelled,$seller->id,'cancel:retry');

        $this->assertSame('cancelled',(string)$again->status);
        $this->assertSame(10,app(HoldingReservationService::class)->availableQuantity($holding->fresh()));
        $this->assertDatabaseHas('stock_holding_reservations',[
            'reservation_key'=>'secondary:listing:listing:1:shares',
            'status'=>HoldingReservation::RELEASED,
        ]);
    }

    public function test_new_listing_is_blocked_when_secondary_feature_is_disabled(): void
    {
        config(['stock.secondary_market_enabled'=>false]);
        [$seller,$stock]=$this->holding(10);
        $this->expectException(\RuntimeException::class);
        app(SecondaryListingService::class)->create($seller->id,$stock->id,2,25,null,null,'single_winner',now()->addHour(),'listing:disabled');
    }

    private function holding(int $quantity): array
    {
        $seller=User::factory()->create();
        $stock=Stock::create([
            'issuer_type'=>'earthcoop','startup_valuation'=>1000,'startup_valuation_gol'=>1000,
            'total_shares'=>100,'available_shares'=>100,'base_share_price'=>1,'base_share_price_gol'=>10,
        ]);
        $holding=Holding::create(['user_id'=>$seller->id,'stock_id'=>$stock->id,'quantity'=>$quantity]);
        return [$seller,$stock,$holding];
    }
}
