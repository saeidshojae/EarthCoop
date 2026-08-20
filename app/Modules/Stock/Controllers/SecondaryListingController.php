<?php

namespace App\Modules\Stock\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Stock\Models\Auction;
use App\Modules\Stock\Models\Holding;
use App\Modules\Stock\Services\HoldingReservationService;
use App\Modules\Stock\Services\SecondaryListingService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class SecondaryListingController extends Controller
{
    public function __construct(
        private readonly SecondaryListingService $listings,
        private readonly HoldingReservationService $reservations,
    ) {}

    public function create(Request $request, Holding $holding)
    {
        abort_unless((int)$holding->user_id === (int)$request->user()->id, 403);
        $holding->loadMissing('stock');
        $available=$this->reservations->availableQuantity($holding);

        return view('Stock::secondary_listing_create', [
            'holding'=>$holding,
            'availableQuantity'=>$available,
            'secondaryEnabled'=>(bool)config('stock.secondary_market_enabled',false),
            'listingKey'=>(string)Str::uuid(),
        ]);
    }

    public function store(Request $request, Holding $holding)
    {
        abort_unless((int)$holding->user_id === (int)$request->user()->id, 403);
        $data=$request->validate([
            'listing_key'=>'required|uuid',
            'quantity'=>'required|integer|min:1',
            'base_price_gol'=>'required|integer|min:1',
            'min_bid_gol'=>'nullable|integer|min:1',
            'max_bid_gol'=>'nullable|integer|min:1',
            'type'=>'required|in:single_winner,uniform_price,pay_as_bid',
            'duration_hours'=>'required|integer|min:1|max:720',
            'info'=>'nullable|string|max:1000',
        ]);

        try {
            $auction=$this->listings->create(
                (int)$request->user()->id,
                (int)$holding->stock_id,
                (int)$data['quantity'],
                (int)$data['base_price_gol'],
                isset($data['min_bid_gol'])?(int)$data['min_bid_gol']:null,
                isset($data['max_bid_gol'])?(int)$data['max_bid_gol']:null,
                (string)$data['type'],
                now()->addHours((int)$data['duration_hours']),
                (string)$data['listing_key'],
                $data['info']??null,
            );
            return redirect()->route('auction.show',$auction)->with('success','عرضه ثانویه ایجاد شد و سهام فروشنده برای این حراج رزرو شد.');
        } catch (\Throwable $e) {
            return back()->withInput()->with('error','ایجاد عرضه ثانویه انجام نشد: '.$e->getMessage());
        }
    }

    public function cancel(Request $request, Auction $auction)
    {
        try {
            $this->listings->cancel($auction,(int)$request->user()->id,'seller-cancel:'.$auction->id.':'.Str::uuid());
            return redirect()->route('holding.index')->with('success','عرضه ثانویه لغو و سهام رزروشده آزاد شد.');
        } catch (\Throwable $e) {
            return back()->with('error','لغو عرضه انجام نشد: '.$e->getMessage());
        }
    }
}
