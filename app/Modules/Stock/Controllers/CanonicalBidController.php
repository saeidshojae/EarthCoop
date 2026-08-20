<?php

namespace App\Modules\Stock\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\NajmBahar\Models\Account;
use App\Modules\Stock\Models\Auction;
use App\Modules\Stock\Models\Bid;
use App\Modules\Stock\Services\StockBidAcceptanceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class CanonicalBidController extends Controller
{
    public function __construct(private readonly StockBidAcceptanceService $bids) {}

    public function store(Request $request, Auction $auction): RedirectResponse
    {
        $user = Auth::user();
        abort_unless($user, 403);

        if (! $auction->hasCanonicalGolPricing()) {
            return back()->with('error', 'این مسیر فقط برای حراج‌های canonical با قیمت‌گذاری Gol فعال است.');
        }

        $data = $request->validate([
            'price_gol' => ['required', 'integer', 'min:1'],
            'quantity' => ['required', 'integer', 'min:1'],
            'acceptance_key' => ['required', 'string', 'max:191'],
        ]);

        $account = Account::query()
            ->where('user_id', $user->id)
            ->where('type', 'user')
            ->where('status', 1)
            ->orderBy('id')
            ->first();

        if (! $account) {
            return back()->withInput()->with('error', 'حساب اصلی فعال نجم بهار برای ثبت پیشنهاد پیدا نشد.');
        }

        try {
            $bid = $this->bids->acceptActiveBaharBid(
                (int) $user->id,
                (string) $account->account_number,
                $auction,
                (int) $data['price_gol'],
                (int) $data['quantity'],
                (string) $data['acceptance_key'],
                ['source' => 'web_canonical_bid']
            );

            return back()->with('success', "پیشنهاد #{$bid->id} ثبت شد و مبلغ آن از بهار فعال شما رزرو شد.");
        } catch (\Throwable $e) {
            report($e);
            return back()->withInput()->with('error', $e->getMessage());
        }
    }

    public function destroy(Request $request, Bid $bid): RedirectResponse
    {
        $user = Auth::user();
        abort_unless($user && (int) $bid->user_id === (int) $user->id, 403);

        try {
            $this->bids->cancelActiveBaharBid(
                $bid,
                (int) $user->id,
                'canonical-bid:' . $bid->id . ':cancel'
            );

            return redirect()->route('auction.show', $bid->auction_id)
                ->with('success', 'پیشنهاد لغو شد و رزرو بهار فعال آزاد شد.');
        } catch (\Throwable $e) {
            report($e);
            return back()->with('error', $e->getMessage());
        }
    }
}
