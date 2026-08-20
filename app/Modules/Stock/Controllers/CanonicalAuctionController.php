<?php

namespace App\Modules\Stock\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\NajmBahar\Models\Account;
use App\Modules\NajmBahar\Services\ActiveBaharReservationService;
use App\Modules\Stock\Models\Auction;
use App\Modules\Stock\Services\CanonicalAuctionCloseService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;

class CanonicalAuctionController extends Controller
{
    public function show(Auction $auction, ActiveBaharReservationService $reservations)
    {
        if (! $auction->hasCanonicalGolPricing()) {
            $auction->load(['stock','bids.user']);
            $userBids=$auction->bids()->where('user_id',Auth::id())->get();
            $orderBook=$auction->bids->sort(function($a,$b){
                $priceA=$a->price??0; $priceB=$b->price??0;
                if($priceA==$priceB) return strtotime($a->created_at)<=>strtotime($b->created_at);
                return $priceB<=>$priceA;
            })->values();
            return view('Stock::auction_show',compact('auction','userBids','orderBook'));
        }

        $auction->load(['stock','bids.user']);
        $userBids=$auction->bids()->where('user_id',Auth::id())->orderByDesc('id')->get();
        $orderBook=$auction->bids()->where('status','active')->whereNotNull('price_gol')
            ->orderByDesc('price_gol')->orderBy('created_at')->orderBy('id')->get();

        $najmAccount=null; $availableActive=null;
        if (Auth::check()) {
            $najmAccount=Account::query()->where('user_id',Auth::id())->where('type','user')->where('status',1)->orderBy('id')->first();
            if ($najmAccount) $availableActive=$reservations->availableActive($najmAccount);
        }

        return view('Stock::auction_show_canonical',compact('auction','userBids','orderBook','najmAccount','availableActive'));
    }

    public function close(Auction $auction, CanonicalAuctionCloseService $closer): RedirectResponse
    {
        try {
            $result=$closer->close($auction);
            return back()->with('success','تسویه canonical انجام شد. تعداد سهام تخصیص‌یافته: '.number_format((int)($result['allocated_shares']??0)));
        } catch (\Throwable $e) {
            report($e);
            return back()->with('error',$e->getMessage());
        }
    }
}
