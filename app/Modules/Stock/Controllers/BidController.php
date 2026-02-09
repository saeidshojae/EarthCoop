<?php
namespace App\Modules\Stock\Controllers;

use App\Modules\Stock\Models\Bid;
use App\Modules\Stock\Models\Auction;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;
use App\Modules\Stock\Services\WalletService;
use Illuminate\Support\Facades\DB;

class BidController extends Controller
{
    // ثبت پیشنهاد کاربر در حراج
    public function store(Request $request, $auctionId)
    {
        $data = $request->validate([
            'price' => 'required|numeric|min:0',
            'quantity' => 'required|integer|min:1',
        ]);

        $userId = Auth::id();
        $data['auction_id'] = $auctionId;
        $data['user_id'] = $userId;
        $data['status'] = 'active';

        $total = $data['price'] * $data['quantity'];

        $walletService = app(WalletService::class);
        $wallet = $walletService->getOrCreateWallet($userId);

        try {
            return DB::transaction(function() use ($data, $walletService, $wallet, $total) {
                // hold the required amount
                $walletService->hold($wallet, $total, 'Hold for new bid');

                $bid = Bid::create($data);

                // Reputation: bid placed (for controllers that create bids directly)
                try {
                    $reputation = app(\App\Services\ReputationService::class);
                    $user = \App\Models\User::find($data['user_id']);
                    if ($user) {
                        $reputation->applyAction($user, 'bid_placed', ['auction_id' => $auctionId, 'bid_id' => $bid->id], $bid->id, 'stock.bid');
                    }
                } catch (\Exception $e) {
                    \Log::warning('Reputation bid_placed failed (BidController): ' . $e->getMessage());
                }

                return redirect()->back()->with('success', 'پیشنهاد شما ثبت شد');
            });
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'ثبت پیشنهاد با مشکل مواجه شد: ' . $e->getMessage());
        }
    }

    // show edit form for a user's bid
    public function edit(Bid $bid)
    {
        $user = Auth::user();
        if (!$user || $bid->user_id !== $user->id) {
            abort(403);
        }

        $auction = $bid->auction;
        if (!$auction || !$auction->isActive() || $bid->status !== 'active') {
            return redirect()->back()->with('error', 'این پیشنهاد قابل ویرایش نیست');
        }

        return view('Stock::bid_edit', compact('bid', 'auction'));
    }

    // update an existing bid (allow user to modify price/quantity while auction is running)
    public function update(Request $request, Bid $bid)
    {
        $user = Auth::user();
        if (!$user || $bid->user_id !== $user->id) {
            abort(403);
        }

        $auction = $bid->auction;
        if (!$auction || !$auction->isActive() || $bid->status !== 'active') {
            return redirect()->back()->with('error', 'این پیشنهاد قابل ویرایش نیست');
        }

        $data = $request->validate([
            'price' => 'required|numeric|min:0',
            'quantity' => 'required|integer|min:1',
        ]);

        // adjust user's held amount according to new total value
        $walletService = app(WalletService::class);
        $wallet = $walletService->getOrCreateWallet($user->id);

        $oldTotal = $bid->price * $bid->quantity;
        $newTotal = $data['price'] * $data['quantity'];

        return DB::transaction(function() use ($bid, $data, $oldTotal, $newTotal, $walletService, $wallet, $auction) {
            if ($newTotal > $oldTotal) {
                $diff = $newTotal - $oldTotal;
                // hold additional amount
                $walletService->hold($wallet, $diff, "Additional hold for bid #{$bid->id}", $bid);
            } elseif ($newTotal < $oldTotal) {
                $diff = $oldTotal - $newTotal;
                // release excess
                $walletService->release($wallet, $diff, "Released excess for bid #{$bid->id}", $bid);
            }

            $bid->update([
                'price' => $data['price'],
                'quantity' => $data['quantity'],
            ]);

            return redirect()->route('auction.show', $auction)->with('success', 'پیشنهاد با موفقیت بروزرسانی شد');
        });
    }

    // allow user to cancel their bid before auction closes
    public function destroy(Bid $bid)
    {
        $user = Auth::user();
        if (!$user || $bid->user_id !== $user->id) {
            abort(403);
        }

        $auction = $bid->auction;
        if (!$auction || !$auction->isActive() || $bid->status !== 'active') {
            return redirect()->back()->with('error', 'این پیشنهاد قابل حذف نیست');
        }

        return DB::transaction(function() use ($bid, $auction) {
            // release held amount
            $walletService = app(WalletService::class);
            $wallet = $walletService->getOrCreateWallet($bid->user_id);
            $walletService->release($wallet, $bid->total_value, "Bid cancelled #{$bid->id}", $bid);

            $bid->update(['status' => 'lost']);
            
            // Dispatch events
            $user = Auth::user();
            if ($user) {
                event(new \App\Events\BidCancelled($bid, $auction, $user));
                event(new \App\Events\WalletReleased($wallet, $user, $bid->total_value, $bid, "Bid cancelled #{$bid->id}"));
            }
            
            return redirect()->route('auction.show', $auction)->with('success', 'پیشنهاد شما لغو شد');
        });
    }
}
