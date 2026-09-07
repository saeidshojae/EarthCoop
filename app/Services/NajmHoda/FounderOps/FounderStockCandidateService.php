<?php

namespace App\Services\NajmHoda\FounderOps;

use App\Modules\Stock\Models\Auction;

class FounderStockCandidateService
{
    public function candidates(int $limit = 10): array
    {
        return Auction::query()->with('stock:id,issuer_type,issuer_id')
            ->where(function ($q) {
                $q->whereIn('status',['running','scheduled','settling'])
                    ->orWhere(function ($qq) { $qq->whereNotNull('ends_at')->where('ends_at','<',now())->where('status','!=','settled'); });
            })
            ->orderByRaw("CASE WHEN ends_at IS NOT NULL AND ends_at < ? AND status <> 'settled' THEN 0 ELSE 1 END", [now()])
            ->orderBy('ends_at')
            ->limit(max(1,min($limit,50)))
            ->get()
            ->map(fn (Auction $auction) => [
                'auction_id'=>(int)$auction->id,'status'=>(string)$auction->status,
                'issuer_type'=>(string)($auction->stock?->issuer_type ?? ''),'market_type'=>(string)($auction->market_type ?? ''),
                'supply_source'=>(string)($auction->supply_source ?? ''),'settlement_channel'=>(string)($auction->settlement_channel ?? ''),
                'quote_unit'=>(string)($auction->quote_unit ?? ''),'ends_at'=>$auction->ends_at?->toIso8601String(),
                'urgency'=>$auction->isExpired() && (string)$auction->status !== 'settled' ? 'high' : 'normal',
            ])->values()->all();
    }
}
