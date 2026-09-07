<?php
namespace App\Modules\Stock\Controllers;

use App\Modules\Stock\Models\Auction;
use App\Modules\Stock\Models\Stock;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

class AuctionController extends Controller
{
    public function index()
    {
        $auctions = Auction::orderByDesc('id')->get();
        $stock = Stock::first();
        return view('Stock::auction_list', compact('auctions', 'stock'));
    }

    public function create()
    {
        $stock = Stock::first();
        return view('Stock::auction_create', compact('stock'));
    }

    public function store(Request $request)
    {
        foreach (['start_time', 'end_time', 'ends_at'] as $field) {
            $visible = $field . '_visible';
            if ($request->filled($visible) && !$request->filled($field)) {
                try {
                    $dt = \Morilog\Jalali\CalendarUtils::createCarbonFromFormat('Y/m/d H:i', $request->input($visible));
                    $request->merge([$field => $dt->format('Y-m-d H:i:s')]);
                } catch (\Exception $e) {
                }
            }
        }
        if ($request->filled('start_time')) {
            try {
                $greg = \Morilog\Jalali\Jalalian::fromFormat('Y/m/d H:i', $request->input('start_time'))->toCarbon();
                $request->merge(['start_time' => $greg->format('Y-m-d H:i:s')]);
            } catch (\Exception $e) {
            }
        }
        if ($request->filled('end_time')) {
            try {
                $greg = \Morilog\Jalali\Jalalian::fromFormat('Y/m/d H:i', $request->input('end_time'))->toCarbon();
                $request->merge(['end_time' => $greg->format('Y-m-d H:i:s')]);
            } catch (\Exception $e) {
            }
        }
        $data = $request->validate([
            'stock_id' => 'required|exists:stocks,id',
            'shares_count' => 'required|integer',
            'base_price' => 'required|numeric',
            'start_time' => 'required|date',
            'end_time' => 'required|date|after:start_time',
            'info' => 'nullable|string',
        ]);
        Auction::create($data);
        return redirect()->route('auction.index')->with('success', 'حراج جدید ثبت شد');
    }

    public function show(Auction $auction)
    {
        $auction->load(['stock', 'bids.user']);
        $userBids = $auction->bids()->where('user_id', auth()->id())->get();
        $orderBook = $auction->bids->sort(function($a, $b) {
            $priceA = $a->price ?? 0;
            $priceB = $b->price ?? 0;
            if ($priceA == $priceB) {
                return strtotime($a->created_at) <=> strtotime($b->created_at);
            }
            return $priceB <=> $priceA;
        })->values();

        return view('Stock::auction_show', compact('auction', 'userBids', 'orderBook'));
    }

    public function adminShow(Auction $auction)
    {
        $auction->load(['stock', 'bids.user']);
        $orderBook = $auction->bids->sort(function($a, $b) {
            $priceA = $a->price ?? 0;
            $priceB = $b->price ?? 0;
            if ($priceA == $priceB) {
                return strtotime($a->created_at) <=> strtotime($b->created_at);
            }
            return $priceB <=> $priceA;
        })->values();

        $settlementStats = null;
        if (in_array($auction->status, ['settled', 'settling'])) {
            $allBids = $auction->bids;
            $winners = $allBids->where('status', 'won');
            $losers = $allBids->where('status', 'lost');
            $totalWinners = $winners->count();
            $totalLosers = $losers->count();
            $totalSharesAllocated = $winners->sum('quantity');
            $totalRevenue = $winners->sum(fn($bid) => ($bid->price ?? 0) * ($bid->quantity ?? 0));
            $averageWinningPrice = $winners->count() > 0 ? $winners->avg('price') : 0;
            $highestWinningPrice = $winners->max('price') ?? 0;
            $lowestWinningPrice = $winners->min('price') ?? 0;
            $totalLostBids = $losers->sum('quantity');
            $totalLostValue = $losers->sum(fn($bid) => ($bid->price ?? 0) * ($bid->quantity ?? 0));
            $winnerList = $winners->map(function($bid) {
                return [
                    'user' => $bid->user,
                    'user_id' => $bid->user_id,
                    'price' => $bid->price ?? 0,
                    'quantity' => $bid->quantity ?? 0,
                    'total_value' => ($bid->price ?? 0) * ($bid->quantity ?? 0),
                    'created_at' => $bid->created_at,
                ];
            })->sortByDesc('price')->values();
            $clearingPrice = null;
            if ($auction->type === 'uniform_price' && $winners->count() > 0) {
                $clearingPrice = $winners->sortBy('price')->first()->price ?? null;
            }
            $settlementStats = [
                'total_participants' => $allBids->unique('user_id')->count(),
                'total_bids' => $allBids->count(),
                'total_winners' => $totalWinners,
                'total_losers' => $totalLosers,
                'total_shares_allocated' => $totalSharesAllocated,
                'total_revenue' => $totalRevenue,
                'average_winning_price' => $averageWinningPrice,
                'highest_winning_price' => $highestWinningPrice,
                'lowest_winning_price' => $lowestWinningPrice,
                'clearing_price' => $clearingPrice,
                'total_lost_bids' => $totalLostBids,
                'total_lost_value' => $totalLostValue,
                'winner_list' => $winnerList,
                'loser_list' => $losers->map(function($bid) {
                    return [
                        'user' => $bid->user,
                        'user_id' => $bid->user_id,
                        'price' => $bid->price ?? 0,
                        'quantity' => $bid->quantity ?? 0,
                        'total_value' => ($bid->price ?? 0) * ($bid->quantity ?? 0),
                        'created_at' => $bid->created_at,
                    ];
                })->sortByDesc('price')->values(),
            ];
        }

        return view('Stock::admin_auction_show', compact('auction', 'orderBook', 'settlementStats'));
    }

    public function placeBid(Request $request, Auction $auction)
    {
        if (!auth()->check()) {
            return back()->with('error', 'لطفاً ابتدا وارد شوید');
        }
        $data = $request->validate([
            'price' => 'required|numeric|min:0',
            'quantity' => 'required|integer|min:1',
        ]);
        try {
            $auctionService = app(\App\Modules\Stock\Services\AuctionService::class);
            $auctionService->validateAndPlaceBid(auth()->id(), $auction, $data['price'], $data['quantity']);
            try {
                $stock = $auction->stock;
                if ($stock) {
                    $stock->recalculateMarketData();
                }
            } catch (\Exception $e) {
                \Log::warning('Stock recalc failed after bid: ' . $e->getMessage());
            }
            return back()->with('success', 'پیشنهاد شما با موفقیت ثبت شد');
        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function adminIndex(Request $request)
    {
        $query = Auction::with('bids');
        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }
        if ($request->filled('type')) {
            $query->where('type', $request->input('type'));
        }
        if ($request->filled('q')) {
            $q = $request->input('q');
            $query->where(function($q2) use ($q) {
                $q2->where('info', 'like', "%{$q}%")->orWhere('type', 'like', "%{$q}%");
            });
        }
        if ($request->filled('date_from')) {
            $df = $request->input('date_from');
            if (strpos($df, '/') !== false) {
                try {
                    $g = \Morilog\Jalali\Jalalian::fromFormat('Y/m/d', $df)->toCarbon();
                    $df = $g->format('Y-m-d') . ' 00:00:00';
                } catch (\Exception $e) {
                }
            } elseif (preg_match('/^\d{4}-\d{2}-\d{2}$/', $df)) {
                $df .= ' 00:00:00';
            }
            $query->where('start_time', '>=', $df);
        }
        if ($request->filled('date_to')) {
            $dt = $request->input('date_to');
            if (strpos($dt, '/') !== false) {
                try {
                    $g = \Morilog\Jalali\Jalalian::fromFormat('Y/m/d', $dt)->toCarbon();
                    $dt = $g->format('Y-m-d') . ' 23:59:59';
                } catch (\Exception $e) {
                }
            } elseif (preg_match('/^\d{4}-\d{2}-\d{2}$/', $dt)) {
                $dt .= ' 23:59:59';
            }
            $query->where('start_time', '<=', $dt);
        }
        if ($request->filled('price_min')) {
            $query->where('base_price', '>=', $request->input('price_min'));
        }
        if ($request->filled('price_max')) {
            $query->where('base_price', '<=', $request->input('price_max'));
        }
        if ($request->filled('volume_min')) {
            $query->where('shares_count', '>=', $request->input('volume_min'));
        }
        if ($request->filled('volume_max')) {
            $query->where('shares_count', '<=', $request->input('volume_max'));
        }
        $sortBy = $request->input('sort_by', 'id');
        $sortOrder = $request->input('sort_order', 'desc');
        if (in_array($sortBy, ['id', 'shares_count', 'base_price', 'start_time', 'ends_at', 'status', 'type', 'created_at'])) {
            $query->orderBy($sortBy, $sortOrder);
        }

        $auctions = $query->paginate(25)->appends($request->except('page'));
        $allAuctions = Auction::with('bids')->get();
        $stats = [
            'total_auctions' => $allAuctions->count(),
            'running_auctions' => $allAuctions->where('status', 'running')->count(),
            'scheduled_auctions' => $allAuctions->where('status', 'scheduled')->count(),
            'settled_auctions' => $allAuctions->whereIn('status', ['settled', 'completed'])->count(),
            'canceled_auctions' => $allAuctions->whereIn('status', ['canceled', 'cancelled'])->count(),
            'total_bids' => $allAuctions->sum(fn($a) => $a->bids->count()),
            'total_volume' => $allAuctions->sum(fn($a) => $a->bids->sum('quantity')),
            'total_capital' => $allAuctions->sum(fn($a) => $a->bids->sum(fn($b) => ($b->price ?? 0) * ($b->quantity ?? 0))),
        ];
        $chartData = $this->getAuctionChartData($allAuctions);
        $statusCounts = [
            'running' => $stats['running_auctions'],
            'scheduled' => $stats['scheduled_auctions'],
            'settled' => $stats['settled_auctions'],
            'canceled' => $stats['canceled_auctions'],
        ];
        $totalVolume = $auctions->sum(fn($a) => $a->bids->sum('quantity'));
        $auctions->getCollection()->transform(function($auction) {
            $bids = $auction->bids;
            $auction->bids_count = $bids->count();
            $auction->highest_bid = $bids->max('price') ?? null;
            $auction->lowest_bid = $bids->min('price') ?? null;
            $auction->total_bid_volume = $bids->sum('quantity');
            $auction->order_book = $bids->where('status', 'active')->sortByDesc('price')->take(5)->values();
            return $auction;
        });
        if ($request->filled('bids_min')) {
            $auctions->setCollection($auctions->getCollection()->reject(fn($auction) => $auction->bids_count < $request->input('bids_min')));
        }
        if ($request->filled('bids_max')) {
            $auctions->setCollection($auctions->getCollection()->reject(fn($auction) => $auction->bids_count > $request->input('bids_max')));
        }

        return view('Stock::admin_auction_list', compact('auctions', 'stats', 'statusCounts', 'totalVolume', 'chartData'));
    }

    private function getAuctionChartData($auctions)
    {
        $labels = [];
        $volumes = [];
        $prices = [];
        $counts = [];
        for ($i = 11; $i >= 0; $i--) {
            $date = now()->subMonths($i);
            $monthLabel = \Morilog\Jalali\Jalalian::fromCarbon($date)->format('Y/m');
            $monthAuctions = $auctions->filter(fn($auction) => $auction->start_time && $auction->start_time->format('Y-m') === $date->format('Y-m'));
            $monthVolume = $monthAuctions->sum(fn($a) => $a->bids->sum('quantity'));
            $monthPrices = $monthAuctions->flatMap(fn($auction) => $auction->bids->pluck('price')->filter());
            $monthAvgPrice = $monthPrices->count() > 0 ? $monthPrices->avg() : 0;
            $labels[] = $monthLabel;
            $volumes[] = $monthVolume;
            $prices[] = round($monthAvgPrice, 2);
            $counts[] = $monthAuctions->count();
        }
        return compact('labels', 'volumes', 'prices', 'counts');
    }

    public function bulkAction(Request $request)
    {
        $request->validate([
            'action' => 'required|in:start,close,delete,export',
            'auction_ids' => 'required|array',
            'auction_ids.*' => 'exists:auctions,id'
        ]);
        $auctions = Auction::whereIn('id', $request->auction_ids)->get();
        switch ($request->action) {
            case 'start':
                $count = 0;
                foreach ($auctions as $auction) {
                    if ($auction->status === 'scheduled') {
                        $auction->update(['status' => 'running']);
                        $count++;
                    }
                }
                return back()->with('success', $count . ' حراج شروع شدند');
            case 'close':
                $count = 0;
                $auctionService = app(\App\Modules\Stock\Services\AuctionService::class);
                foreach ($auctions as $auction) {
                    if ($auction->status === 'running') {
                        try {
                            $auctionService->closeAuction($auction);
                            $count++;
                        } catch (\Exception $e) {
                            \Log::error('Error closing auction ' . $auction->id . ': ' . $e->getMessage());
                        }
                    }
                }
                return back()->with('success', $count . ' حراج بسته شدند');
            case 'delete':
                $count = $auctions->count();
                foreach ($auctions as $auction) {
                    $auction->bids()->delete();
                    $auction->delete();
                }
                return back()->with('success', $count . ' حراج حذف شدند');
            case 'export':
                return back()->with('info', 'قابلیت Export دسته‌ای به زودی اضافه می‌شود');
        }
    }

    public function adminCreate()
    {
        $stock = Stock::first();
        return view('Stock::admin_auction_create', compact('stock'));
    }

    public function adminEdit(Auction $auction)
    {
        $stock = Stock::first();
        return view('Stock::admin_auction_create', compact('stock', 'auction'));
    }

    public function adminUpdate(Request $request, Auction $auction)
    {
        $this->normalizeAuctionDates($request);
        $data = $this->validateCanonicalPrimaryOffering($request, false);
        $stock = Stock::find($data['stock_id']);
        if ($stock) {
            $maxAllowed = (int) $stock->available_shares + (int) $auction->shares_count;
            if ((int) $data['shares_count'] > $maxAllowed) {
                return back()->withInput()->with('error', 'تعداد سهام برای حراج نمی‌تواند بیش از میزان قابل عرضه باشد');
            }
        }
        $auction->update($this->canonicalPrimaryOfferingData($data));
        return redirect()->route('admin.auction.index')->with('success', 'حراج با موفقیت بروزرسانی شد');
    }

    public function adminDestroy(Auction $auction)
    {
        if ($auction->bids()->count() > 0) {
            return redirect()->route('admin.auction.index')->with('error', 'تنها حراج‌هایی که هیچ شرکت‌کننده‌ای ندارند قابل حذف هستند');
        }
        if ($auction->status === 'running') {
            return redirect()->route('admin.auction.index')->with('error', 'حراج‌های فعال قابل حذف نیستند');
        }
        $auction->delete();
        return redirect()->route('admin.auction.index')->with('success', 'حراج با موفقیت حذف شد');
    }

    public function adminStore(Request $request)
    {
        $this->normalizeAuctionDates($request);
        $data = $this->validateCanonicalPrimaryOffering($request, true);
        $stock = Stock::find($data['stock_id']);
        if ($stock && (int) $data['shares_count'] > (int) $stock->available_shares) {
            return back()->withInput()->with('error', 'تعداد سهام برای حراج نمی‌تواند بیش از میزان قابل عرضه باشد');
        }
        if (($data['settlement_channel'] ?? '') === 'external_irr' && strtolower((string) ($stock->issuer_type ?? '')) !== 'earthcoop') {
            return back()->withInput()->withErrors(['settlement_channel' => 'تسویه خارجی فقط برای عرضه اولیه خزانه EarthCoop مجاز است.']);
        }
        $canonical = $this->canonicalPrimaryOfferingData($data);
        $canonical['status'] = 'scheduled';
        Auction::create($canonical);
        return redirect()->route('admin.auction.index')->with('success', 'حراج جدید ذخیره شد');
    }

    private function normalizeAuctionDates(Request $request): void
    {
        foreach (['start_time', 'end_time', 'ends_at'] as $field) {
            $visible = $field . '_visible';
            if ($request->filled($visible) && !$request->filled($field)) {
                try {
                    $dt = \Morilog\Jalali\CalendarUtils::createCarbonFromFormat('Y/m/d H:i', $request->input($visible));
                    $request->merge([$field => $dt->format('Y-m-d H:i:s')]);
                } catch (\Exception $e) {
                }
            }
        }
    }

    private function validateCanonicalPrimaryOffering(Request $request, bool $requireEndsAt): array
    {
        return $request->validate([
            'stock_id' => 'required|exists:stocks,id',
            'shares_count' => 'required|integer|min:1',
            'base_price_gol' => 'required|integer|min:1',
            'settlement_channel' => 'required|in:active_bahar,external_irr',
            'start_time' => 'required|date',
            'end_time' => 'required|date|after:start_time',
            'ends_at' => ($requireEndsAt ? 'required' : 'nullable') . '|date|after:start_time',
            'type' => 'required|in:single_winner,uniform_price,pay_as_bid',
            'settlement_mode' => 'required|in:auto,manual',
            'lot_size' => 'required|integer|min:1',
            'info' => 'nullable|string',
        ]);
    }

    private function canonicalPrimaryOfferingData(array $data): array
    {
        $baseGol = (int) $data['base_price_gol'];
        return [
            'stock_id' => (int) $data['stock_id'],
            'market_type' => 'primary',
            'supply_source' => 'treasury',
            'settlement_channel' => $data['settlement_channel'],
            'quote_unit' => 'gol',
            'shares_count' => (int) $data['shares_count'],
            'base_price_gol' => $baseGol,
            'base_price' => $baseGol / 100,
            'start_time' => $data['start_time'],
            'end_time' => $data['end_time'],
            'ends_at' => $data['ends_at'] ?? $data['end_time'],
            'type' => $data['type'],
            'settlement_mode' => $data['settlement_mode'],
            'lot_size' => (int) $data['lot_size'],
            'info' => $data['info'] ?? null,
        ];
    }

    public function startAuction(Auction $auction)
    {
        if ($auction->status !== 'scheduled') {
            return back()->with('error', 'فقط حراج‌های برنامه‌ریزی شده قابل شروع هستند');
        }
        if ($auction->ends_at && $auction->ends_at->isPast()) {
            return back()->with('error', 'حراجی که زمان پایان آن گذشته است قابل شروع نیست');
        }
        $auction->update(['status' => 'running']);
        try {
            $notificationService = app(\App\Services\NotificationService::class);
            $users = \App\Models\User::whereHas('roles', function($q) {
                $q->where('slug', '!=', 'super-admin');
            })->orWhere('is_admin', false)->get();
            $notificationService->notifyMany(
                $users,
                'حراج جدید شروع شد',
                "حراج #{$auction->id} شروع شد. فرصت پیشنهاد دادن تا " . \Morilog\Jalali\Jalalian::fromCarbon($auction->ends_at)->format('Y/m/d H:i') . ' است.',
                route('auction.show', $auction),
                'success',
                ['auction_id' => $auction->id]
            );
        } catch (\Exception $e) {
            \Log::warning('Failed to send auction start notification: ' . $e->getMessage());
        }
        return back()->with('success', 'حراج شروع شد');
    }

    public function closeAuction(Auction $auction)
    {
        if ($auction->status !== 'running') {
            return back()->with('error', 'فقط حراج‌های فعال قابل بستن هستند');
        }
        $auctionService = app(\App\Modules\Stock\Services\AuctionService::class);
        $participatingUsers = $auction->bids()->distinct('user_id')->pluck('user_id');
        try {
            $results = $auctionService->closeAuction($auction);
            if (isset($results['requires_manual_approval']) && $results['requires_manual_approval']) {
                return back()->with('info', 'حراج بسته شد. لطفاً تسویه را از صفحه جزئیات حراج تایید کنید.');
            }
            if ($participatingUsers->count() > 0 && !isset($results['requires_manual_approval'])) {
                try {
                    $notificationService = app(\App\Services\NotificationService::class);
                    $notificationService->notifyMany(
                        $participatingUsers,
                        'حراج به پایان رسید',
                        "حراج #{$auction->id} بسته و تسویه شد. نتایج را بررسی کنید.",
                        route('auction.show', $auction),
                        'info',
                        ['auction_id' => $auction->id]
                    );
                } catch (\Exception $e) {
                    \Log::warning('Failed to send auction close notification: ' . $e->getMessage());
                }
            }
        } catch (\Exception $e) {
            \Log::error('Error closing auction ' . $auction->id . ': ' . $e->getMessage());
            return back()->with('error', 'خطا در بستن حراج: ' . $e->getMessage());
        }
        return back()->with('success', 'حراج بسته و تسویه شد');
    }

    public function manualSettleAuction(Auction $auction)
    {
        if ($auction->status !== 'settling') {
            return back()->with('error', 'فقط حراج‌های در حال تسویه قابل تایید هستند');
        }
        if ($auction->settlement_mode !== 'manual') {
            return back()->with('error', 'این حراج برای تسویه دستی تنظیم نشده است');
        }
        $auctionService = app(\App\Modules\Stock\Services\AuctionService::class);
        $participatingUsers = $auction->bids()->distinct('user_id')->pluck('user_id');
        try {
            $auctionService->manualSettleAuction($auction);
            if ($participatingUsers->count() > 0) {
                try {
                    $notificationService = app(\App\Services\NotificationService::class);
                    $notificationService->notifyMany(
                        $participatingUsers,
                        'حراج به پایان رسید',
                        "حراج #{$auction->id} بسته و تسویه شد. نتایج را بررسی کنید.",
                        route('auction.show', $auction),
                        'info',
                        ['auction_id' => $auction->id]
                    );
                } catch (\Exception $e) {
                    \Log::warning('Failed to send auction settlement notification: ' . $e->getMessage());
                }
            }
            return back()->with('success', 'تسویه حراج با موفقیت انجام شد');
        } catch (\Exception $e) {
            \Log::error('Error manually settling auction ' . $auction->id . ': ' . $e->getMessage());
            return back()->with('error', 'خطا در تسویه حراج: ' . $e->getMessage());
        }
    }

    public function export(Auction $auction)
    {
        $auction->load('bids');
        $fileName = 'auction-' . $auction->id . '-export.csv';
        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"{$fileName}\"",
        ];
        $callback = function() use ($auction) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['Auction ID', 'Stock ID', 'Shares Count', 'Base Price', 'Status', 'Start Time', 'Ends At']);
            fputcsv($handle, [
                $auction->id,
                $auction->stock_id,
                $auction->shares_count,
                $auction->base_price,
                $auction->status,
                $auction->start_time ? verta($auction->start_time)->format('Y/m/d H:i') : '',
                $auction->ends_at ? verta($auction->ends_at)->format('Y/m/d H:i') : '',
            ]);
            fputcsv($handle, []);
            fputcsv($handle, ['BID_ID', 'USER_ID', 'PRICE', 'QUANTITY', 'STATUS', 'CREATED_AT']);
            $bidsForExport = $auction->bids->sort(function($a, $b) {
                $priceA = $a->price ?? 0;
                $priceB = $b->price ?? 0;
                if ($priceA == $priceB) {
                    return strtotime($a->created_at) <=> strtotime($b->created_at);
                }
                return $priceB <=> $priceA;
            })->values();
            foreach ($bidsForExport as $b) {
                fputcsv($handle, [
                    $b->id,
                    $b->user_id,
                    $b->price,
                    $b->quantity,
                    $b->status,
                    $b->created_at ? verta($b->created_at)->format('Y/m/d H:i') : '',
                ]);
            }
            fclose($handle);
        };
        return response()->stream($callback, 200, $headers);
    }
}
