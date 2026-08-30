<?php

namespace App\Modules\Stock\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Stock\Models\Auction;
use App\Modules\Stock\Models\Holding;
use App\Modules\Stock\Models\Stock;
use Illuminate\Support\Facades\Auth;

final class CanonicalStockBookController extends Controller
{
    public function show()
    {
        $stock = Stock::query()->first();

        $auctions = collect();
        $userHoldings = null;

        if ($stock) {
            $auctions = Auction::query()
                ->where('stock_id', $stock->id)
                ->whereIn('status', ['scheduled', 'running'])
                ->where('ends_at', '>', now())
                ->orderBy('start_time')
                ->get();

            if (Auth::check()) {
                $userHoldings = Holding::query()
                    ->where('user_id', Auth::id())
                    ->where('stock_id', $stock->id)
                    ->get();
            }
        }

        return view('Stock::stock_dashboard', [
            'stock' => $stock,
            'auctions' => $auctions,
            'soldShares' => 0,
            'userHoldings' => $userHoldings,
            'walletData' => null,
        ]);
    }
}
