<?php
namespace App\Modules\Stock\Controllers;

use App\Modules\Stock\Models\Auction;
use App\Modules\Stock\Models\Stock;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

class AuctionController extends Controller
{
    // لیست حراج‌ها
    public function index()
    {
        $auctions = Auction::orderByDesc('id')->get();
        $stock = Stock::first();
        return view('Stock::auction_list', compact('auctions', 'stock'));
    }

    // فرم ایجاد حراج جدید
    public function create()
    {
        $stock = Stock::first();
        return view('Stock::auction_create', compact('stock'));
    }

    // ذخیره حراج جدید
    public function store(Request $request)
    {
        // If a visible Jalali field was used by the client, convert it to Gregorian before validation
        foreach (['start_time', 'end_time', 'ends_at'] as $field) {
            $visible = $field . '_visible';
            if ($request->filled($visible) && !$request->filled($field)) {
                try {
                    $dt = \Morilog\Jalali\CalendarUtils::createCarbonFromFormat('Y/m/d H:i', $request->input($visible));
                    $request->merge([$field => $dt->format('Y-m-d H:i:s')]);
                } catch (\Exception $e) {
                    // ignore conversion errors; validation will catch invalid date
                }
            }
        }
        // Accept Jalali date strings from the UI and convert them to Gregorian before validation
        if ($request->filled('start_time')) {
            try {
                $greg = \Morilog\Jalali\Jalalian::fromFormat('Y/m/d H:i', $request->input('start_time'))->toCarbon();
                $request->merge(['start_time' => $greg->format('Y-m-d H:i:s')]);
            } catch (\Exception $e) {
                // not a Jalali string — leave as-is
            }
        }
        if ($request->filled('end_time')) {
            try {
                $greg = \Morilog\Jalali\Jalalian::fromFormat('Y/m/d H:i', $request->input('end_time'))->toCarbon();
                $request->merge(['end_time' => $greg->format('Y-m-d H:i:s')]);
            } catch (\Exception $e) {
                // not a Jalali string — leave as-is
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
    
    // نمایش جزئیات حراج
    public function show(Auction $auction)
    {
        $auction->load(['stock', 'bids.user']);
        $userBids = $auction->bids()->where('user_id', auth()->id())->get();
        
        // build full order book (all bids for admin view) — sort in PHP to avoid SQL column name mismatches
        $orderBook = $auction->bids->sort(function($a, $b) {
            $priceA = $a->price ?? 0;
            $priceB = $b->price ?? 0;
            if ($priceA == $priceB) {
                // compare by created_at asc
                return strtotime($a->created_at) <=> strtotime($b->created_at);
            }
            // price desc
            return $priceB <=> $priceA;
        })->values();

        return view('Stock::auction_show', compact('auction', 'userBids', 'orderBook'));
    }
    
    // نمایش جزئیات حراج برای پنل مدیریت
    public function adminShow(Auction $auction)
    {
        $auction->load(['stock', 'bids.user']);
        
        // build full order book (all bids for admin view) — sort in PHP to avoid SQL column name mismatches
        $orderBook = $auction->bids->sort(function($a, $b) {
            $priceA = $a->price ?? 0;
            $priceB = $b->price ?? 0;
            if ($priceA == $priceB) {
                // compare by created_at asc
                return strtotime($a->created_at) <=> strtotime($b->created_at);
            }
            // price desc
            return $priceB <=> $priceA;
        })->values();
        
        // محاسبه آمار شرکت‌کنندگان برای گزارش تسویه
        $settlementStats = null;
        if (in_array($auction->status, ['settled', 'settling'])) {
            $allBids = $auction->bids;
            $winners = $allBids->where('status', 'won');
            $losers = $allBids->where('status', 'lost');
            $activeBids = $allBids->where('status', 'active');
            
            // محاسبه آمار برندگان
            $totalWinners = $winners->count();
            $totalSharesAllocated = $winners->sum('quantity');
            $totalRevenue = $winners->sum(function($bid) {
                return ($bid->price ?? 0) * ($bid->quantity ?? 0);
            });
            $averageWinningPrice = $winners->count() > 0 ? $winners->avg('price') : 0;
            $highestWinningPrice = $winners->max('price') ?? 0;
            $lowestWinningPrice = $winners->min('price') ?? 0;
            
            // محاسبه آمار بازندگان
            $totalLosers = $losers->count();
            $totalLostBids = $losers->sum('quantity');
            $totalLostValue = $losers->sum(function($bid) {
                return ($bid->price ?? 0) * ($bid->quantity ?? 0);
            });
            
            // لیست برندگان با جزئیات
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
            
            // محاسبه clearing price برای uniform price auctions
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
    
    // ثبت پیشنهاد
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
            $bid = $auctionService->validateAndPlaceBid(
                auth()->id(),
                $auction,
                $data['price'],
                $data['quantity']
            );
            // پس از ثبت پیشنهاد، بروزرسانی اطلاعات بازار در سهام مربوطه
            try {
                $stock = $auction->stock;
                if ($stock) {
                    $stock->recalculateMarketData();
                }
            } catch (\Exception $e) {
                // ignore recalc errors for now but log if needed
                \Log::warning('Stock recalc failed after bid: ' . $e->getMessage());
            }

            return back()->with('success', 'پیشنهاد شما با موفقیت ثبت شد');
            
        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    // لیست حراج‌ها برای پنل مدیریت
    public function adminIndex(Request $request)
    {
        // Filters: status, type, q (search), date_from, date_to, price_min, price_max, volume_min, volume_max, bids_min, bids_max, sort_by, sort_order
        $query = \App\Modules\Stock\Models\Auction::with('bids');

        // فیلتر وضعیت
        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }
        
        // فیلتر نوع حراج
        if ($request->filled('type')) {
            $query->where('type', $request->input('type'));
        }
        
        // جستجو در info و type
        if ($request->filled('q')) {
            $q = $request->input('q');
            $query->where(function($q2) use ($q) {
                $q2->where('info', 'like', "%{$q}%")
                   ->orWhere('type', 'like', "%{$q}%");
            });
        }
        
        // فیلتر تاریخ شروع
        if ($request->filled('date_from')) {
            $df = $request->input('date_from');
            // allow Jalali input like 1404/08/09 or Gregorian YYYY-MM-DD
            if (strpos($df, '/') !== false) {
                try {
                    $g = \Morilog\Jalali\Jalalian::fromFormat('Y/m/d', $df)->toCarbon();
                    $df = $g->format('Y-m-d') . ' 00:00:00';
                } catch (\Exception $e) {
                    // ignore and proceed
                }
            } elseif (preg_match('/^\d{4}-\d{2}-\d{2}$/', $df)) {
                $df = $df . ' 00:00:00';
            }
            $query->where('start_time', '>=', $df);
        }
        
        // فیلتر تاریخ پایان
        if ($request->filled('date_to')) {
            $dt = $request->input('date_to');
            if (strpos($dt, '/') !== false) {
                try {
                    $g = \Morilog\Jalali\Jalalian::fromFormat('Y/m/d', $dt)->toCarbon();
                    $dt = $g->format('Y-m-d') . ' 23:59:59';
                } catch (\Exception $e) {
                }
            } elseif (preg_match('/^\d{4}-\d{2}-\d{2}$/', $dt)) {
                $dt = $dt . ' 23:59:59';
            }
            $query->where('start_time', '<=', $dt);
        }
        
        // فیلتر بازه قیمت پایه
        if ($request->filled('price_min')) {
            $query->where('base_price', '>=', $request->input('price_min'));
        }
        if ($request->filled('price_max')) {
            $query->where('base_price', '<=', $request->input('price_max'));
        }
        
        // فیلتر بازه حجم سهام
        if ($request->filled('volume_min')) {
            $query->where('shares_count', '>=', $request->input('volume_min'));
        }
        if ($request->filled('volume_max')) {
            $query->where('shares_count', '<=', $request->input('volume_max'));
        }
        
        // مرتب‌سازی
        $sortBy = $request->input('sort_by', 'id');
        $sortOrder = $request->input('sort_order', 'desc');
        
        // اعمال مرتب‌سازی
        if (in_array($sortBy, ['id', 'shares_count', 'base_price', 'start_time', 'ends_at', 'status', 'type', 'created_at'])) {
            $query->orderBy($sortBy, $sortOrder);
        }

        // فیلتر تعداد پیشنهادها (بعد از pagination باید اعمال شود)
        $auctions = $query->paginate(25)->appends($request->except('page'));

        // محاسبه آمار کلی
        $allAuctions = \App\Modules\Stock\Models\Auction::with('bids')->get();
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
        
        // داده‌های نمودار (آخرین 12 ماه)
        $chartData = $this->getAuctionChartData($allAuctions);
        
        // آمار برای نمایش در کارت‌ها
        $statusCounts = [
            'running' => $stats['running_auctions'],
            'scheduled' => $stats['scheduled_auctions'],
            'settled' => $stats['settled_auctions'],
            'canceled' => $stats['canceled_auctions'],
        ];
        
        // محاسبه حجم کل در لیست فعلی
        $totalVolume = $auctions->sum(fn($a) => $a->bids->sum('quantity'));

        // compute quick stats per auction
        $auctions->getCollection()->transform(function($auction) {
            $bids = $auction->bids;
            $auction->bids_count = $bids->count();
            $auction->highest_bid = $bids->max('price') ?? null;
            $auction->lowest_bid = $bids->min('price') ?? null;
            $auction->total_bid_volume = $bids->sum('quantity');
            // order book: active bids sorted by price desc (top 5)
            $auction->order_book = $bids->where('status', 'active')
                                      ->sortByDesc('price')
                                      ->take(5)
                                      ->values();
            return $auction;
        });
        
        // فیلتر تعداد پیشنهادها (بعد از محاسبه stats)
        if ($request->filled('bids_min')) {
            $auctions->getCollection()->transform(function($auction) use ($request) {
                if ($auction->bids_count < $request->input('bids_min')) {
                    $auction->should_skip = true;
                }
                return $auction;
            });
            $auctions->setCollection($auctions->getCollection()->reject(function($auction) {
                return isset($auction->should_skip) && $auction->should_skip;
            }));
        }
        if ($request->filled('bids_max')) {
            $auctions->getCollection()->transform(function($auction) use ($request) {
                if ($auction->bids_count > $request->input('bids_max')) {
                    $auction->should_skip = true;
                }
                return $auction;
            });
            $auctions->setCollection($auctions->getCollection()->reject(function($auction) {
                return isset($auction->should_skip) && $auction->should_skip;
            }));
        }

        return view('Stock::admin_auction_list', compact('auctions', 'stats', 'statusCounts', 'totalVolume', 'chartData'));
    }
    
    // تهیه داده‌های نمودار برای حراج‌ها
    private function getAuctionChartData($auctions)
    {
        $labels = [];
        $volumes = [];
        $prices = [];
        $counts = [];
        
        // آخرین 12 ماه
        for ($i = 11; $i >= 0; $i--) {
            $date = now()->subMonths($i);
            $monthLabel = \Morilog\Jalali\Jalalian::fromCarbon($date)->format('Y/m');
            
            $monthAuctions = $auctions->filter(function($auction) use ($date) {
                return $auction->start_time && 
                       $auction->start_time->format('Y-m') === $date->format('Y-m');
            });
            
            $monthVolume = $monthAuctions->sum(function($a) {
                return $a->bids->sum('quantity');
            });
            $monthPrices = $monthAuctions->flatMap(function($auction) {
                return $auction->bids->pluck('price')->filter();
            });
            $monthAvgPrice = $monthPrices->count() > 0 ? $monthPrices->avg() : 0;
            
            $labels[] = $monthLabel;
            $volumes[] = $monthVolume;
            $prices[] = round($monthAvgPrice, 2);
            $counts[] = $monthAuctions->count();
        }
        
        return [
            'labels' => $labels,
            'volumes' => $volumes,
            'prices' => $prices,
            'counts' => $counts
        ];
    }
    
    // عملیات دسته‌ای بر روی حراج‌ها
    public function bulkAction(Request $request)
    {
        $request->validate([
            'action' => 'required|in:start,close,delete,export',
            'auction_ids' => 'required|array',
            'auction_ids.*' => 'exists:auctions,id'
        ]);

        $auctionIds = $request->auction_ids;
        $auctions = Auction::whereIn('id', $auctionIds)->get();

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
                            // status توسط closeAuction به 'settled' تغییر می‌کند
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
                    // حذف پیشنهادها و داده‌های مرتبط
                    $auction->bids()->delete();
                    $auction->delete();
                }
                return back()->with('success', $count . ' حراج حذف شدند');
            
            case 'export':
                // Export انتخابی - می‌توانیم بعداً پیاده‌سازی کنیم
                return back()->with('info', 'قابلیت Export دسته‌ای به زودی اضافه می‌شود');
        }
    }

    // فرم ایجاد حراج جدید برای پنل مدیریت
    public function adminCreate()
    {
        $stock = \App\Modules\Stock\Models\Stock::first();
        return view('Stock::admin_auction_create', compact('stock'));
    }

    // فرم ویرایش حراج برای پنل مدیریت
    public function adminEdit(Auction $auction)
    {
        $stock = \App\Modules\Stock\Models\Stock::first();
        return view('Stock::admin_auction_create', compact('stock', 'auction'));
    }

    // بروزرسانی حراج از پنل مدیریت
    public function adminUpdate(Request $request, Auction $auction)
    {
        // Convert any Jalali visible inputs to Gregorian before validation
        foreach (['start_time', 'end_time', 'ends_at'] as $field) {
            $visible = $field . '_visible';
            if ($request->filled($visible) && !$request->filled($field)) {
                try {
                    $dt = \Morilog\Jalali\CalendarUtils::createCarbonFromFormat('Y/m/d H:i', $request->input($visible));
                    $request->merge([$field => $dt->format('Y-m-d H:i:s')]);
                } catch (\Exception $e) {
                    // ignore conversion errors; validation will catch invalid date
                }
            }
        }
        $data = $request->validate([
            'stock_id' => 'required|exists:stocks,id',
            'shares_count' => 'required|integer|min:1',
            'base_price' => 'required|numeric|min:0',
            'start_time' => 'required|date',
            'end_time' => 'required|date|after:start_time',
            'ends_at' => 'nullable|date|after:start_time',
            'type' => 'required|in:single_winner,uniform_price,pay_as_bid',
            'settlement_mode' => 'required|in:auto,manual',
            'min_bid' => 'nullable|numeric|min:0',
            'max_bid' => 'nullable|numeric|min:0|gte:min_bid',
            'lot_size' => 'required|integer|min:1',
            'channel_id' => 'nullable|exists:groups,id',
            'info' => 'nullable|string',
        ]);

        // Ensure updated shares_count does not exceed stock available_shares (+ include current auction shares)
        $stock = \App\Modules\Stock\Models\Stock::find($data['stock_id']);
        if ($stock) {
            $maxAllowed = $stock->available_shares + $auction->shares_count;
            if ($data['shares_count'] > $maxAllowed) {
                return back()->with('error', 'تعداد سهام برای حراج نمی‌تواند بیش از میزان قابل عرضه باشد');
            }
        }

        $auction->update($data);

        return redirect()->route('admin.auction.index')->with('success', 'حراج با موفقیت بروزرسانی شد');
    }

    // حذف حراج از پنل مدیریت
    public function adminDestroy(Auction $auction)
    {
        // فقط اجازه حذف اگر هیچ شرکت‌کننده‌ای (پیشنهاد) وجود نداشته باشد
        $bidsCount = $auction->bids()->count();
        if ($bidsCount > 0) {
            return redirect()->route('admin.auction.index')->with('error', 'تنها حراج‌هایی که هیچ شرکت‌کننده‌ای ندارند قابل حذف هستند');
        }

        // جلوگیری از حذف حراج‌های فعال (اضافی به بررسی بالا)
        if ($auction->status === 'running') {
            return redirect()->route('admin.auction.index')->with('error', 'حراج‌های فعال قابل حذف نیستند');
        }

        $auction->delete();

        return redirect()->route('admin.auction.index')->with('success', 'حراج با موفقیت حذف شد');
    }

    // ذخیره حراج جدید از پنل مدیریت
    public function adminStore(Request $request)
    {
        // Convert visible Jalali inputs to Gregorian before validation
        foreach (['start_time', 'end_time', 'ends_at'] as $field) {
            $visible = $field . '_visible';
            if ($request->filled($visible) && !$request->filled($field)) {
                try {
                    $dt = \Morilog\Jalali\CalendarUtils::createCarbonFromFormat('Y/m/d H:i', $request->input($visible));
                    $request->merge([$field => $dt->format('Y-m-d H:i:s')]);
                } catch (\Exception $e) {
                    // ignore conversion errors; validation will catch invalid date
                }
            }
        }
        $data = $request->validate([
            'stock_id' => 'required|exists:stocks,id',
            'shares_count' => 'required|integer|min:1',
            'base_price' => 'required|numeric|min:0',
            'start_time' => 'required|date',
            'end_time' => 'required|date|after:start_time',
            'ends_at' => 'required|date|after:start_time',
            'type' => 'required|in:single_winner,uniform_price,pay_as_bid',
            'settlement_mode' => 'required|in:auto,manual',
            'min_bid' => 'nullable|numeric|min:0',
            'max_bid' => 'nullable|numeric|min:0|gte:min_bid',
            'lot_size' => 'required|integer|min:1',
            'channel_id' => 'nullable|exists:groups,id',
            'info' => 'nullable|string',
        ]);
        
        // Ensure shares_count does not exceed stock available_shares
        $stock = \App\Modules\Stock\Models\Stock::find($data['stock_id']);
        if ($stock && isset($data['shares_count']) && $data['shares_count'] > $stock->available_shares) {
            return back()->with('error', 'تعداد سهام برای حراج نمی‌تواند بیش از میزان قابل عرضه باشد');
        }

        $data['status'] = 'scheduled';
        Auction::create($data);

        return redirect()->route('admin.auction.index')->with('success', 'حراج جدید ذخیره شد');
    }
    
    // شروع حراج
    public function startAuction(Auction $auction)
    {
        if ($auction->status !== 'scheduled') {
            return back()->with('error', 'فقط حراج‌های برنامه‌ریزی شده قابل شروع هستند');
        }
        
        $auction->update(['status' => 'running']);
        
        // ارسال اعلان به کاربران (در صورت نیاز)
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
    
    // بستن دستی حراج
    public function closeAuction(Auction $auction)
    {
        if ($auction->status !== 'running') {
            return back()->with('error', 'فقط حراج‌های فعال قابل بستن هستند');
        }
        
        $auctionService = app(\App\Modules\Stock\Services\AuctionService::class);
        
        // دریافت لیست کاربران شرکت‌کننده
        $participatingUsers = $auction->bids()->distinct('user_id')->pluck('user_id');
        
        try {
            $results = $auctionService->closeAuction($auction);
            
            // اگر تسویه دستی باشد، فقط وضعیت به settling تغییر می‌کند
            if (isset($results['requires_manual_approval']) && $results['requires_manual_approval']) {
                return back()->with('info', 'حراج بسته شد. لطفاً تسویه را از صفحه جزئیات حراج تایید کنید.');
            }
            
            // ارسال اعلان به کاربران شرکت‌کننده (فقط در صورت تسویه خودکار)
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
    
    // تسویه دستی حراج (توسط ادمین)
    public function manualSettleAuction(Auction $auction)
    {
        if ($auction->status !== 'settling') {
            return back()->with('error', 'فقط حراج‌های در حال تسویه قابل تایید هستند');
        }
        
        if ($auction->settlement_mode !== 'manual') {
            return back()->with('error', 'این حراج برای تسویه دستی تنظیم نشده است');
        }
        
        $auctionService = app(\App\Modules\Stock\Services\AuctionService::class);
        
        // دریافت لیست کاربران شرکت‌کننده
        $participatingUsers = $auction->bids()->distinct('user_id')->pluck('user_id');
        
        try {
            $results = $auctionService->manualSettleAuction($auction);
            
            // ارسال اعلان به کاربران شرکت‌کننده
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

    // Export auction stats and full order book as CSV
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
            // header rows
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

            // Blank line
            fputcsv($handle, []);

            // Order book header
            fputcsv($handle, ['BID_ID', 'USER_ID', 'PRICE', 'QUANTITY', 'STATUS', 'CREATED_AT']);
            // fetch bids collection and sort in PHP to be resilient to different DB schemas
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
