<?php
namespace App\Modules\Stock\Controllers;

use App\Modules\Stock\Models\Stock;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Modules\Stock\Models\Auction;
use App\Modules\Stock\Models\Bid;
use App\Modules\Stock\Models\StockTransaction;
use App\Modules\Stock\Models\Holding;
use App\Modules\Stock\Services\WalletService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;

class StockController extends Controller
{
    // نمایش اطلاعات پایه سهام
    public function index()
    {
        $stock = Stock::first();
        return view('Stock.Views.stock_info', compact('stock'));
    }

    // فرم تنظیم اطلاعات پایه
    public function create()
    {
        return view('Stock.Views.stock_create');
    }

    // ذخیره اطلاعات پایه سهام
    public function store(Request $request)
    {
        $data = $request->validate([
            'startup_valuation' => 'required|numeric',
            'total_shares' => 'required|integer',
            'available_shares' => 'nullable|integer',
            'base_share_price' => 'required|numeric',
            'info' => 'nullable|string',
        ]);
        $stock = Stock::create($data);
        return redirect()->route('stock.index')->with('success', 'اطلاعات سهام ثبت شد');
    }

    // نمایش اطلاعات پایه سهام برای پنل مدیریت
    public function adminIndex()
    {
        $stock = Stock::first();
        
        // محاسبه آمار
        $stats = $this->calculateStockStats($stock);
        
        // هشدارها
        $alerts = $this->getAlerts($stock);
        
        return view('stock.admin_stock_info', compact('stock', 'stats', 'alerts'));
    }
    
    // دریافت هشدارها
    private function getAlerts($stock)
    {
        $alerts = [];
        
        if (!$stock) {
            return $alerts;
        }
        
        // هشدار کمبود سهام قابل عرضه
        $auctions = \App\Modules\Stock\Models\Auction::whereIn('status', ['scheduled', 'running'])->sum('shares_count');
        $availableAfterAuctions = ($stock->available_shares ?? 0) - $auctions;
        
        if ($availableAfterAuctions < ($stock->available_shares ?? 0) * 0.1) {
            $alerts[] = [
                'type' => 'warning',
                'title' => 'هشدار کمبود سهام قابل عرضه',
                'message' => 'تعداد سهام قابل عرضه باقی‌مانده کمتر از ۱۰٪ است. لطفاً موجودی را بررسی کنید.',
                'icon' => 'fa-exclamation-triangle'
            ];
        }
        
        // هشدار حراج‌های در حال پایان
        $endingAuctions = \App\Modules\Stock\Models\Auction::where('status', 'running')
            ->where('ends_at', '<=', now()->addHours(24))
            ->where('ends_at', '>', now())
            ->count();
        
        if ($endingAuctions > 0) {
            $alerts[] = [
                'type' => 'info',
                'title' => 'حراج‌های در حال پایان',
                'message' => "{$endingAuctions} حراج در ۲۴ ساعت آینده به پایان می‌رسند.",
                'icon' => 'fa-clock'
            ];
        }
        
        // هشدار حراج‌های در حال شروع
        $startingAuctions = \App\Modules\Stock\Models\Auction::where('status', 'scheduled')
            ->where('start_time', '<=', now()->addHours(24))
            ->where('start_time', '>', now())
            ->count();
        
        if ($startingAuctions > 0) {
            $alerts[] = [
                'type' => 'success',
                'title' => 'حراج‌های در حال شروع',
                'message' => "{$startingAuctions} حراج در ۲۴ ساعت آینده شروع می‌شوند.",
                'icon' => 'fa-play-circle'
            ];
        }
        
        // هشدار پیشنهادهای در انتظار بررسی
        $pendingBids = \App\Modules\Stock\Models\Bid::where('status', 'active')
            ->whereHas('auction', function($q) {
                $q->where('status', 'running');
            })
            ->count();
        
        if ($pendingBids > 100) {
            $alerts[] = [
                'type' => 'warning',
                'title' => 'تعداد بالای پیشنهادات',
                'message' => "{$pendingBids} پیشنهاد فعال در حراج‌های جاری وجود دارد. لطفاً بررسی کنید.",
                'icon' => 'fa-hand-pointer'
            ];
        }
        
        return $alerts;
    }
    
    // محاسبه آمار سهام
    private function calculateStockStats($stock)
    {
        if (!$stock) {
            return [
                'total_auctions' => 0,
                'active_auctions' => 0,
                'completed_auctions' => 0,
                'scheduled_auctions' => 0,
                'canceled_auctions' => 0,
                'total_bids' => 0,
                'active_bids' => 0,
                'total_volume' => 0,
                'total_capital' => 0,
                'average_price' => 0,
                'highest_price' => 0,
                'lowest_price' => 0,
                'total_investors' => 0,
                'sold_shares' => 0,
                'chart_data' => [
                    'labels' => [],
                    'volumes' => [],
                    'prices' => [],
                    'dates' => []
                ]
            ];
        }
        
        $auctions = Auction::with('bids')->get();
        $bids = Bid::all();
        $holdings = \App\Modules\Stock\Models\Holding::where('stock_id', $stock->id)->get();
        
        // آمار حراج‌ها
        $totalAuctions = $auctions->count();
        $activeAuctions = $auctions->where('status', 'running')->count();
        $completedAuctions = $auctions->whereIn('status', ['settled', 'completed'])->count();
        $scheduledAuctions = $auctions->where('status', 'scheduled')->count();
        $canceledAuctions = $auctions->whereIn('status', ['canceled', 'cancelled'])->count();
        
        // آمار پیشنهادها
        $totalBids = $bids->count();
        $activeBids = $bids->where('status', 'active')->count();
        $totalVolume = $bids->sum('quantity');
        
        // آمار مالی
        $totalCapital = $bids->sum(function($bid) {
            return ($bid->price ?? 0) * ($bid->quantity ?? 0);
        });
        
        $prices = $bids->pluck('price')->filter()->values();
        $averagePrice = $prices->count() > 0 ? $prices->avg() : 0;
        $highestPrice = $prices->count() > 0 ? $prices->max() : 0;
        $lowestPrice = $prices->count() > 0 ? $prices->min() : 0;
        
        // آمار سرمایه‌گذاران
        $totalInvestors = $holdings->unique('user_id')->count();
        $soldShares = $holdings->sum('shares_count');
        
        // داده‌های نمودار (آخرین 12 ماه)
        $chartData = $this->getChartData($auctions);
        
        return [
            'total_auctions' => $totalAuctions,
            'active_auctions' => $activeAuctions,
            'completed_auctions' => $completedAuctions,
            'scheduled_auctions' => $scheduledAuctions,
            'canceled_auctions' => $canceledAuctions,
            'total_bids' => $totalBids,
            'active_bids' => $activeBids,
            'total_volume' => $totalVolume,
            'total_capital' => $totalCapital,
            'average_price' => $averagePrice,
            'highest_price' => $highestPrice,
            'lowest_price' => $lowestPrice,
            'total_investors' => $totalInvestors,
            'sold_shares' => $soldShares,
            'chart_data' => $chartData
        ];
    }
    
    // تهیه داده‌های نمودار
    private function getChartData($auctions)
    {
        $labels = [];
        $volumes = [];
        $prices = [];
        $dates = [];
        
        // آخرین 12 ماه
        for ($i = 11; $i >= 0; $i--) {
            $date = now()->subMonths($i);
            $monthLabel = \Morilog\Jalali\Jalalian::fromCarbon($date)->format('Y/m');
            
            $monthAuctions = $auctions->filter(function($auction) use ($date) {
                return $auction->start_time && 
                       $auction->start_time->format('Y-m') === $date->format('Y-m');
            });
            
            $monthVolume = $monthAuctions->sum('shares_count');
            $monthPrices = $monthAuctions->flatMap(function($auction) {
                return $auction->bids->pluck('price')->filter();
            });
            $monthAvgPrice = $monthPrices->count() > 0 ? $monthPrices->avg() : 0;
            
            $labels[] = $monthLabel;
            $volumes[] = $monthVolume;
            $prices[] = round($monthAvgPrice, 2);
            $dates[] = $date->format('Y-m');
        }
        
        return [
            'labels' => $labels,
            'volumes' => $volumes,
            'prices' => $prices,
            'dates' => $dates
        ];
    }

    // فرم ویرایش اطلاعات پایه سهام برای پنل مدیریت
    public function adminCreate()
    {
        $stock = Stock::first();
        return view('stock.admin_stock_create', compact('stock'));
    }

    // ذخیره اطلاعات پایه سهام از پنل مدیریت
    public function adminStore(Request $request)
    {
        $data = $request->validate([
            'startup_valuation' => 'required|numeric',
            'total_shares' => 'required|integer',
            'available_shares' => 'nullable|integer',
            'base_share_price' => 'required|numeric',
            'info' => 'nullable|string',
        ]);
        // مقادیر مالی در فرم و دیتابیس به صورت تومان هستند.

        $stock = Stock::first();
        $oldPrice = $stock ? $stock->base_share_price : null;
        $oldValuation = $stock ? $stock->startup_valuation : null;
        
        if ($stock) {
            $stock->update($data);
        } else {
            $stock = Stock::create($data);
        }
        
        // Dispatch event if price changed
        if ($oldPrice !== null && $oldPrice != $stock->base_share_price) {
            event(new \App\Events\StockPriceChanged(
                $stock,
                $oldPrice,
                $stock->base_share_price,
                $oldValuation,
                $stock->startup_valuation
            ));
        }
        
        return redirect()->route('admin.stock.index')->with('success', 'اطلاعات سهام ذخیره شد');
    }

    // دفتر سهام (داشبورد بازار) برای کاربران: کارت‌های اطلاعات پایه، فهرست حراج‌ها، بازار جانبی، کیف سهام و کیف پول
    public function book()
    {
        $stock = Stock::first();

        // attempt to recalculate market data to present up-to-date base price
        try {
            if ($stock) {
                $stock->recalculateMarketData();
            }
        } catch (\Exception $e) {
            // ignore recalc errors but log
            \Log::warning('Stock recalc failed on dashboard: ' . $e->getMessage());
        }

        // fetch auctions (running and upcoming)
        $auctions = Auction::where('stock_id', optional($stock)->id ?? null)
                    ->whereIn('status', ['scheduled','running'])
                    ->orderBy('start_time')
                    ->get();

        // enrich auctions with quick stats used by the UI
        $auctions->transform(function($auction) {
            // determine which price column exists to safely order at SQL level
            try {
                $priceColumn = \Schema::hasColumn('bids', 'price') ? 'price' : (\Schema::hasColumn('bids', 'bid_price') ? 'bid_price' : null);
            } catch (\Exception $e) {
                $priceColumn = null;
            }

            if ($priceColumn) {
                $highest = $auction->bids()->where('status', 'active')->orderByDesc($priceColumn)->first();
            } else {
                // fallback to collection ordering if no known DB column
                $highest = $auction->bids->where('status', 'active')->sortByDesc(function($b){ return $b->price ?? 0;})->first();
            }

            if ($highest) {
                $auction->highest_price = $highest->price;
                // compute highest quantity at that price using collection (uses model accessors so it's resilient)
                $activeBids = $auction->bids->where('status', 'active');
                $auction->highest_quantity = $activeBids->filter(function($b) use ($highest){
                    return (float) $b->price === (float) $highest->price;
                })->sum('quantity');
            } else {
                $auction->highest_price = null;
                $auction->highest_quantity = 0;
            }

            // remaining time components
            $now = Carbon::now();
            if ($auction->ends_at && $auction->ends_at->greaterThan($now)) {
                $diff = $now->diff($auction->ends_at);
                $auction->time_remaining = [
                    'days' => $diff->d,
                    'hours' => $diff->h,
                    'minutes' => $diff->i,
                    'seconds' => $diff->s,
                ];
            } else {
                $auction->time_remaining = null;
            }

            return $auction;
        });

        // sold shares (aggregate from stock transactions)
        $soldShares = 0;
        if ($stock) {
            $soldShares = StockTransaction::whereHas('auction', function($q) use ($stock){
                $q->where('stock_id', $stock->id);
            })->where('type', 'buy')->sum('shares_count');
        }

        $userHoldings = null;
        $walletData = null;
        if (Auth::check()) {
            $userId = Auth::id();
            $userHoldings = Holding::where('user_id', $userId)->where('stock_id', optional($stock)->id ?? 0)->get();
            $walletService = app(WalletService::class);
            $wallet = $walletService->getOrCreateWallet($userId);
            $walletData = [
                'available_balance' => $wallet->available_balance ?? 0,
                'held_amount' => $wallet->held_amount ?? 0,
            ];
        }

        return view('Stock::stock_dashboard', compact('stock','auctions','soldShares','userHoldings','walletData'));
    }
    
    // نمایش فرم هدیه دادن سهام (پنل مدیریت)
    public function showGiftForm()
    {
        $stock = Stock::first();
        $users = \App\Models\User::orderBy('first_name')->orderBy('last_name')->get();
        return view('stock.admin_gift_shares', compact('stock', 'users'));
    }
    
    // هدیه دادن سهام به کاربر(ان) (پنل مدیریت)
    public function giftShares(Request $request)
    {
        $data = $request->validate([
            'user_ids' => 'required|array|min:1',
            'user_ids.*' => 'required|exists:users,id',
            'quantity' => 'required|integer|min:1',
            'description' => 'nullable|string|max:500',
        ]);
        
        $stock = Stock::first();
        if (!$stock) {
            return back()->with('error', 'اطلاعات سهام یافت نشد');
        }
        
        $totalQuantity = $data['quantity'] * count($data['user_ids']);
        
        // بررسی موجودی سهام قابل عرضه
        if ($stock->available_shares < $totalQuantity) {
            return back()->with('error', "تعداد سهام قابل عرضه کافی نیست. موجودی: {$stock->available_shares} سهم، درخواست: {$totalQuantity} سهم");
        }
        
        $holdingService = app(\App\Modules\Stock\Services\HoldingService::class);
        
        try {
            \DB::transaction(function () use ($data, $stock, $totalQuantity, $holdingService) {
                // هدیه دادن سهام به هر کاربر
                foreach ($data['user_ids'] as $userId) {
                    $holding = $holdingService->getOrCreateHolding($userId, $stock->id);
                    $holdingService->credit(
                        $holding,
                        $data['quantity'],
                        $data['description'] ?? "هدیه سهام توسط ادمین - " . auth()->user()->name,
                        null
                    );
                    
                    // Dispatch event
                    $user = \App\Models\User::find($userId);
                    if ($user) {
                        event(new \App\Events\SharesGifted($holding, $user, $stock, $data['quantity'], $data['description'] ?? "هدیه سهام توسط ادمین"));
                    }
                }
                
                // کسر از سهام قابل عرضه
                $stock->decrement('available_shares', $totalQuantity);
                
                // ایجاد رکورد تراکنش سهام (فقط در صورت وجود فیلد stock_id)
                // اگر stock_id در جدول وجود نداشته باشد، از طریق auction_id می‌توان stock را پیدا کرد
                // اما برای هدیه که auction_id = null است، از info استفاده می‌کنیم
                foreach ($data['user_ids'] as $userId) {
                    $transactionData = [
                        'user_id' => $userId,
                        'auction_id' => null,
                        'shares_count' => $data['quantity'],
                        'price' => 0, // هدیه رایگان است
                        'type' => 'buy', // استفاده از 'buy' برای هدیه
                        'info' => ($data['description'] ?? 'هدیه سهام') . ' (هدیه - Stock ID: ' . $stock->id . ')',
                    ];
                    
                    // اگر فیلد stock_id وجود دارد، اضافه می‌کنیم
                    if (\Schema::hasColumn('stock_transactions', 'stock_id')) {
                        $transactionData['stock_id'] = $stock->id;
                    }
                    
                    \App\Modules\Stock\Models\StockTransaction::create($transactionData);
                }
            });
            
            $usersCount = count($data['user_ids']);
            return redirect()->route('admin.stock.index')
                ->with('success', "{$data['quantity']} سهم به {$usersCount} کاربر هدیه داده شد");
        } catch (\Exception $e) {
            \Log::error('Error gifting shares: ' . $e->getMessage());
            return back()->with('error', 'خطا در هدیه دادن سهام: ' . $e->getMessage());
        }
    }
    
    // لیست سهامداران (پنل مدیریت)
    public function shareholders(Request $request)
    {
        $stock = Stock::first();
        if (!$stock) {
            return view('stock.admin_shareholders', ['shareholders' => collect(), 'stock' => null, 'stats' => []]);
        }
        
        // گروه‌بندی holdings بر اساس user_id و stock_id
        $allHoldings = \App\Modules\Stock\Models\Holding::where('stock_id', $stock->id)
            ->where('quantity', '>', 0)
            ->with(['user', 'stock'])
            ->get();
        
        // گروه‌بندی و محاسبه
        $grouped = $allHoldings->groupBy('user_id')->map(function($group) {
            $first = $group->first();
            return (object)[
                'user_id' => $first->user_id,
                'user' => $first->user,
                'total_shares' => $group->sum('quantity'),
                'total_value' => $group->sum(function($h) {
                    return $h->quantity * ($h->stock->base_share_price ?? 0);
                }),
                'holdings' => $group,
            ];
        })->sortByDesc('total_shares')->values();
        
        // Pagination
        $page = $request->get('page', 1);
        $perPage = 50;
        $offset = ($page - 1) * $perPage;
        $shareholders = new \Illuminate\Pagination\LengthAwarePaginator(
            $grouped->slice($offset, $perPage),
            $grouped->count(),
            $perPage,
            $page,
            ['path' => $request->url(), 'query' => $request->query()]
        );
        
        // محاسبه آمار
        $stats = [
            'total_shareholders' => $grouped->count(),
            'total_shares_distributed' => $grouped->sum('total_shares'),
            'total_value' => $grouped->sum('total_value'),
            'average_shares_per_shareholder' => $grouped->count() > 0 ? round($grouped->sum('total_shares') / $grouped->count(), 2) : 0,
        ];
        
        return view('stock.admin_shareholders', compact('shareholders', 'stock', 'stats'));
    }
}
