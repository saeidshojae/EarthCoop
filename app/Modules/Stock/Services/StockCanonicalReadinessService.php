<?php

namespace App\Modules\Stock\Services;

use App\Modules\NajmBahar\Models\Account;
use App\Modules\NajmBahar\Models\ActiveBaharReservation;
use App\Modules\Stock\Models\Auction;
use App\Modules\Stock\Models\Bid;
use App\Modules\Stock\Models\StockSettlementAllocation;
use App\Modules\Stock\Settlement\SettlementChannel;
use App\Modules\Stock\Settlement\SettlementEligibilityPolicy;

class StockCanonicalReadinessService
{
    public function audit(): array
    {
        $blockers=[]; $warnings=[]; $checks=[];
        $canonical=Auction::query()->with('stock')->whereNotNull('base_price_gol')->get();
        $checks['canonical_auction_count']=$canonical->count();

        $capitalRequired=$canonical->contains(function(Auction $auction): bool {
            return (string)($auction->stock?->issuer_type??'')===SettlementEligibilityPolicy::ISSUER_EARTHCOOP
                && (string)$auction->market_type===SettlementEligibilityPolicy::MARKET_PRIMARY
                && (string)$auction->supply_source===SettlementEligibilityPolicy::SUPPLY_TREASURY
                && (string)$auction->settlement_channel===SettlementChannel::ACTIVE_BAHAR;
        });
        $capital=(string)config('stock.earthcoop_capital_account_number','');
        $capitalOk=!$capitalRequired || ($capital!=='' && Account::query()->where('account_number',$capital)->where('status',1)->exists());
        $checks['earthcoop_capital_account_required']=$capitalRequired;
        $checks['earthcoop_capital_account']=$capitalOk;
        if(!$capitalOk) $blockers[]=$this->item('capital_account_missing','EarthCoop capital Najm Bahar account is not configured or active.');

        foreach($canonical as $auction){
            try { $auction->assertSettlementEligible(); }
            catch(\Throwable $e){ $blockers[]=$this->item('auction_boundary_invalid','Canonical auction fails settlement eligibility.',['auction_id'=>$auction->id]); }

            if(strtolower((string)$auction->quote_unit)!=='gol' || (int)$auction->base_price_gol<=0){
                $blockers[]=$this->item('canonical_pricing_invalid','Canonical auction lacks valid Gol pricing.',['auction_id'=>$auction->id]);
            }

            $legacyBids=Bid::query()->where('auction_id',$auction->id)->where(function($q){
                $q->whereNull('price_gol')->orWhereNull('acceptance_key');
            })->count();
            if($legacyBids>0) $blockers[]=$this->item('legacy_bid_in_canonical_auction','Canonical auction contains legacy bids.',['auction_id'=>$auction->id,'count'=>$legacyBids]);

            $issuer=(string)($auction->stock?->issuer_type??'');
            $market=(string)$auction->market_type;
            $supply=(string)$auction->supply_source;
            $channel=(string)$auction->settlement_channel;

            if($market===SettlementEligibilityPolicy::MARKET_SECONDARY && !config('stock.secondary_market_enabled',false)){
                $blockers[]=$this->item('secondary_market_not_cut_over','Secondary-market settlement is intentionally disabled until seller-side share reservation/transfer is implemented.',['auction_id'=>$auction->id]);
            }

            if(in_array($channel,[SettlementChannel::EXTERNAL_IRR,SettlementChannel::EXTERNAL_USD],true) && !config('stock.external_capital_enabled',false)){
                $blockers[]=$this->item('external_capital_not_configured','External capital provider/rate-source cutover is disabled.',['auction_id'=>$auction->id,'channel'=>$channel]);
            }

            if($issuer===SettlementEligibilityPolicy::ISSUER_PROJECT && $market===SettlementEligibilityPolicy::MARKET_PRIMARY && $supply===SettlementEligibilityPolicy::SUPPLY_TREASURY && $channel===SettlementChannel::ACTIVE_BAHAR){
                $blockers[]=$this->item('project_payee_mapping_missing','Project primary Active Bahar settlement has no canonical project payee-account mapping yet.',['auction_id'=>$auction->id,'stock_id'=>$auction->stock_id,'issuer_id'=>$auction->stock?->issuer_id]);
            }
        }

        $reconciliation=StockSettlementAllocation::query()->where('state',StockSettlementAllocation::RECONCILIATION_REQUIRED)->count();
        $checks['reconciliation_required']=$reconciliation;
        if($reconciliation>0) $blockers[]=$this->item('settlement_reconciliation_required','Confirmed money exists with incomplete Stock allocation.',['count'=>$reconciliation]);

        $orphanReservations=ActiveBaharReservation::query()
            ->where('status',ActiveBaharReservation::RESERVED)
            ->where('reference_type','stock_bid')
            ->get()
            ->filter(fn($r)=>!Bid::query()->where('reservation_key',$r->reservation_key)->exists())
            ->count();
        $checks['orphan_stock_bid_reservations']=$orphanReservations;
        if($orphanReservations>0) $blockers[]=$this->item('orphan_stock_bid_reservation','Open Active Bahar reservation has no canonical Bid.',['count'=>$orphanReservations]);

        $expiredActive=Auction::query()->whereNotNull('base_price_gol')->where('status','running')->whereNotNull('ends_at')->where('ends_at','<',now())->count();
        $checks['expired_running_canonical_auctions']=$expiredActive;
        if($expiredActive>0) $warnings[]=$this->item('expired_running_auction','Canonical auctions have expired but remain running.',['count'=>$expiredActive]);

        return [
            'ready'=>count($blockers)===0,
            'generated_at'=>now()->toIso8601String(),
            'summary'=>['blockers'=>count($blockers),'warnings'=>count($warnings),'canonical_auctions'=>$canonical->count()],
            'checks'=>$checks,
            'blockers'=>$blockers,
            'warnings'=>$warnings,
        ];
    }

    private function item(string $code,string $message,array $context=[]): array
    {
        return ['code'=>$code,'message'=>$message,'context'=>$context];
    }
}
