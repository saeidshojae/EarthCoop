<?php
namespace App\Modules\Stock\Controllers;

use App\Modules\Stock\Models\Bid;
use App\Modules\Stock\Models\Auction;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use App\Modules\Stock\Services\WalletService;
use Illuminate\Support\Facades\DB;

class BidController extends Controller
{
    public function store(Request $request, $auctionId)
    {
        $auction = Auction::findOrFail($auctionId);
        if ($auction->hasCanonicalGolPricing()) {
            return redirect()->back()->with('error', 'این حراج از مسیر جدید Gol/Active Bahar استفاده می‌کند و مسیر کیف پول قدیمی برای آن غیرفعال است.');
        }

        $data = $request->validate(['price'=>'required|numeric|min:0','quantity'=>'required|integer|min:1']);
        $userId=Auth::id(); $data['auction_id']=$auctionId; $data['user_id']=$userId; $data['status']='active';
        $total=$data['price']*$data['quantity'];
        $walletService=app(WalletService::class); $wallet=$walletService->getOrCreateWallet($userId);

        try {
            return DB::transaction(function() use ($data,$walletService,$wallet,$total,$auctionId) {
                $walletService->hold($wallet,$total,'Hold for new bid');
                $bid=Bid::create($data);
                try {
                    $reputation=app(\App\Services\ReputationService::class); $user=\App\Models\User::find($data['user_id']);
                    if($user)$reputation->applyAction($user,'bid_placed',['auction_id'=>$auctionId,'bid_id'=>$bid->id],$bid->id,'stock.bid','bid_placed:bid:' . $bid->id . ':user:' . $user->id);
                } catch(\Exception $e){ \Log::warning('Reputation bid_placed failed (BidController): '.$e->getMessage()); }
                return redirect()->back()->with('success','پیشنهاد شما ثبت شد');
            });
        } catch(\Exception $e){ return redirect()->back()->with('error','ثبت پیشنهاد با مشکل مواجه شد: '.$e->getMessage()); }
    }

    public function edit(Bid $bid)
    {
        $user=Auth::user(); if(!$user||$bid->user_id!==$user->id)abort(403);
        $auction=$bid->auction;
        if($auction?->hasCanonicalGolPricing()) return redirect()->back()->with('error','ویرایش پیشنهاد canonical باید از مسیر Active Bahar انجام شود.');
        if(!$auction||!$auction->isActive()||$bid->status!=='active')return redirect()->back()->with('error','این پیشنهاد قابل ویرایش نیست');
        return view('Stock::bid_edit',compact('bid','auction'));
    }

    public function update(Request $request,Bid $bid)
    {
        $user=Auth::user(); if(!$user||$bid->user_id!==$user->id)abort(403);
        $auction=$bid->auction;
        if($auction?->hasCanonicalGolPricing()) return redirect()->back()->with('error','مسیر کیف پول قدیمی اجازه تغییر پیشنهاد canonical را ندارد.');
        if(!$auction||!$auction->isActive()||$bid->status!=='active')return redirect()->back()->with('error','این پیشنهاد قابل ویرایش نیست');
        $data=$request->validate(['price'=>'required|numeric|min:0','quantity'=>'required|integer|min:1']);
        $walletService=app(WalletService::class); $wallet=$walletService->getOrCreateWallet($user->id);
        $oldTotal=$bid->price*$bid->quantity; $newTotal=$data['price']*$data['quantity'];
        return DB::transaction(function() use($bid,$data,$oldTotal,$newTotal,$walletService,$wallet,$auction){
            if($newTotal>$oldTotal)$walletService->hold($wallet,$newTotal-$oldTotal,"Additional hold for bid #{$bid->id}",$bid);
            elseif($newTotal<$oldTotal)$walletService->release($wallet,$oldTotal-$newTotal,"Released excess for bid #{$bid->id}",$bid);
            $bid->update(['price'=>$data['price'],'quantity'=>$data['quantity']]);
            return redirect()->route('auction.show',$auction)->with('success','پیشنهاد با موفقیت بروزرسانی شد');
        });
    }

    public function destroy(Bid $bid)
    {
        $user=Auth::user(); if(!$user||$bid->user_id!==$user->id)abort(403);
        $auction=$bid->auction;
        if($auction?->hasCanonicalGolPricing()) return redirect()->back()->with('error','لغو پیشنهاد canonical باید reservation Active Bahar را آزاد کند؛ مسیر کیف پول قدیمی برای آن غیرفعال است.');
        if(!$auction||!$auction->isActive()||$bid->status!=='active')return redirect()->back()->with('error','این پیشنهاد قابل حذف نیست');
        return DB::transaction(function() use($bid,$auction){
            $walletService=app(WalletService::class); $wallet=$walletService->getOrCreateWallet($bid->user_id);
            $walletService->release($wallet,$bid->total_value,"Bid cancelled #{$bid->id}",$bid); $bid->update(['status'=>'lost']);
            $user=Auth::user(); if($user){ event(new \App\Events\BidCancelled($bid,$auction,$user)); event(new \App\Events\WalletReleased($wallet,$user,$bid->total_value,$bid,"Bid cancelled #{$bid->id}")); }
            return redirect()->route('auction.show',$auction)->with('success','پیشنهاد شما لغو شد');
        });
    }
}
