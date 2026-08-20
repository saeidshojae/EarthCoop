<?php

namespace App\Services\NajmHoda\FounderOps;

use App\Models\FounderFinancialRiskFinding;
use App\Modules\Stock\Models\Auction;
use App\Modules\Stock\Settlement\SettlementEligibilityPolicy;
use App\Modules\Stock\Settlement\SettlementChannel;

class FounderStockRiskService
{
    public function __construct(protected SettlementEligibilityPolicy $eligibility) {}

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

        if (strtolower($quote)!=='gol' || (int)($auction->base_price_gol??0)<=0) {
            $findings[]=$this->finding($auction,'canonical_gol_pricing_missing','medium','Auction is not yet configured with canonical integer Gol pricing.',['quote_unit'=>$quote,'base_price_gol'=>$auction->base_price_gol]);
        }
        if ((int)($auction->stock?->base_share_price_gol??0)<=0 || (int)($auction->stock?->startup_valuation_gol??0)<=0) {
            $findings[]=$this->finding($auction,'stock_gol_valuation_missing','medium','Underlying Stock does not yet have canonical Gol share price and valuation.',['stock_id'=>(int)$auction->stock_id]);
        }
        if ($auction->isExpired() && (string)$auction->status!=='settled') {
            $findings[]=$this->finding($auction,'expired_unsettled','high','Auction is past its end time but is not settled.',['status'=>(string)$auction->status]);
        }
        if ($market===SettlementEligibilityPolicy::MARKET_SECONDARY && $channel!==SettlementChannel::ACTIVE_BAHAR) {
            $findings[]=$this->finding($auction,'secondary_external_forbidden','high','Secondary-market settlement must use Active Bahar only.',['settlement_channel'=>$channel]);
        }

        return ['success'=>true,'status'=>'inspected','auction_id'=>(int)$auction->id,'finding_count'=>count($findings),'findings'=>$findings];
    }

    protected function finding(Auction $auction,string $code,string $severity,string $summary,array $context): array
    {
        $row=FounderFinancialRiskFinding::query()->updateOrCreate(
            ['domain'=>'stock','entity_type'=>'auction','entity_id'=>(int)$auction->id,'risk_code'=>$code],
            ['severity'=>$severity,'status'=>'open','summary'=>$summary,'context'=>$context,'resolved_at'=>null]
        );
        return ['id'=>(int)$row->id,'risk_code'=>$code,'severity'=>$severity,'summary'=>$summary];
    }
}
