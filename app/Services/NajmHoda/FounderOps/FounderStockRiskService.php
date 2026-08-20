<?php

namespace App\Services\NajmHoda\FounderOps;

use App\Models\FounderFinancialRiskFinding;
use App\Modules\NajmBahar\Models\Account;
use App\Modules\Stock\Models\Auction;
use App\Modules\Stock\Models\Bid;
use App\Modules\Stock\Models\HoldingReservation;
use App\Modules\Stock\Models\StockSettlementAllocation;
use App\Modules\Stock\Services\StockPayeeAccountService;
use App\Modules\Stock\Settlement\SettlementEligibilityPolicy;
use App\Modules\Stock\Settlement\SettlementChannel;

class FounderStockRiskService
{
    public function __construct(
        protected SettlementEligibilityPolicy $eligibility,
        protected StockPayeeAccountService $payees,
    ) {}

    public function inspect(Auction $auction): array
    {
        $auction->loadMissing('stock');
        $findings=[];
        $issuer=(string)($auction->stock?->issuer_type??'');
        $market=(string)($auction->market_type??'');
        $supply=(string)($auction->supply_source??'');
        $channel=(string)($auction->settlement_channel??'');
        $quote=(string)($auction->quote_unit??'');

        try { $this->eligibility->assertAllowed($issuer,$market,$supply,$channel); }
        catch (\Throwable $e) { $findings[]=$this->finding($auction,'settlement_boundary_violation','high','Auction settlement classification violates or cannot satisfy the Stock × Najm Bahar boundary.',['issuer_type'=>$issuer,'market_type'=>$market,'supply_source'=>$supply,'settlement_channel'=>$channel]); }

        if (strtolower($quote)!=='gol' || (int)($auction->base_price_gol??0)<=0) $findings[]=$this->finding($auction,'canonical_gol_pricing_missing','medium','Auction is not yet configured with canonical integer Gol pricing.',['quote_unit'=>$quote,'base_price_gol'=>$auction->base_price_gol]);
        if ((int)($auction->stock?->base_share_price_gol??0)<=0 || (int)($auction->stock?->startup_valuation_gol??0)<=0) $findings[]=$this->finding($auction,'stock_gol_valuation_missing','medium','Underlying Stock does not yet have canonical Gol share price and valuation.',['stock_id'=>(int)$auction->stock_id]);
        if ($auction->isExpired() && (string)$auction->status!=='settled') $findings[]=$this->finding($auction,'expired_unsettled','high','Auction is past its end time but is not settled.',['status'=>(string)$auction->status]);
        if ($market===SettlementEligibilityPolicy::MARKET_SECONDARY && $channel!==SettlementChannel::ACTIVE_BAHAR) $findings[]=$this->finding($auction,'secondary_external_forbidden','high','Secondary-market settlement must use Active Bahar only.',['settlement_channel'=>$channel]);

        if ($auction->hasCanonicalGolPricing()) {
            $legacyBids=Bid::query()->where('auction_id',$auction->id)->where(fn($q)=>$q->whereNull('price_gol')->orWhereNull('acceptance_key'))->count();
            if($legacyBids>0) $findings[]=$this->finding($auction,'legacy_bid_in_canonical_auction','high','Canonical auction contains legacy bids and cannot be safely settled.',['count'=>$legacyBids]);

            if($market===SettlementEligibilityPolicy::MARKET_SECONDARY){
                if(!config('stock.secondary_market_enabled',false)) $findings[]=$this->finding($auction,'secondary_cutover_blocked','high','Secondary seller-side backend and listing UX are implemented, but runtime activation remains disabled pending target-environment tests/readiness signoff.',[]);
                if(!$auction->seller_user_id||!$auction->seller_holding_reservation_key) {
                    $findings[]=$this->finding($auction,'secondary_seller_supply_missing','high','Secondary auction has no canonical seller identity/share reservation.',[]);
                } else {
                    $reservation=HoldingReservation::query()->where('reservation_key',$auction->seller_holding_reservation_key)->first();
                    if(!$reservation || (int)$reservation->seller_user_id!==(int)$auction->seller_user_id || (int)$reservation->auction_id!==(int)$auction->id) $findings[]=$this->finding($auction,'secondary_seller_reservation_invalid','high','Seller share reservation is missing or does not match the secondary auction identity.',[]);
                    $sellerAccount=Account::query()->where('user_id',$auction->seller_user_id)->where('type','user')->where('status',1)->exists();
                    if(!$sellerAccount) $findings[]=$this->finding($auction,'secondary_seller_bahar_account_missing','high','Secondary seller has no active Najm Bahar account for proceeds.',['seller_user_id'=>$auction->seller_user_id]);
                }
            }

            if(in_array($channel,[SettlementChannel::EXTERNAL_IRR,SettlementChannel::EXTERNAL_USD],true) && !config('stock.external_capital_enabled',false)) $findings[]=$this->finding($auction,'external_capital_cutover_blocked','high','External provider/rate-source cutover is not enabled for canonical settlement.',['channel'=>$channel]);

            if($market===SettlementEligibilityPolicy::MARKET_PRIMARY && $supply===SettlementEligibilityPolicy::SUPPLY_TREASURY && $channel===SettlementChannel::ACTIVE_BAHAR){
                try { $this->payees->resolvePrimary($auction->stock); }
                catch(\Throwable $e){
                    $code=$issuer===SettlementEligibilityPolicy::ISSUER_PROJECT?'project_payee_mapping_missing':'capital_account_missing';
                    $findings[]=$this->finding($auction,$code,'high',$e->getMessage(),['issuer_type'=>$issuer,'issuer_id'=>$auction->stock?->issuer_id]);
                }
            }

            $reconciliation=StockSettlementAllocation::query()->where('auction_id',$auction->id)->where('state',StockSettlementAllocation::RECONCILIATION_REQUIRED)->count();
            if($reconciliation>0) $findings[]=$this->finding($auction,'reconciliation_required','critical','Confirmed money exists while Stock allocation is incomplete.',['count'=>$reconciliation]);
        }

        return ['success'=>true,'status'=>'inspected','auction_id'=>(int)$auction->id,'finding_count'=>count($findings),'findings'=>$findings];
    }

    protected function finding(Auction $auction,string $code,string $severity,string $summary,array $context): array
    {
        $row=FounderFinancialRiskFinding::query()->updateOrCreate(['domain'=>'stock','entity_type'=>'auction','entity_id'=>(int)$auction->id,'risk_code'=>$code],['severity'=>$severity,'status'=>'open','summary'=>$summary,'context'=>$context,'resolved_at'=>null]);
        return ['id'=>(int)$row->id,'risk_code'=>$code,'severity'=>$severity,'summary'=>$summary];
    }
}
