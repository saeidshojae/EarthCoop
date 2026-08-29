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
use Illuminate\Validation\ValidationException;

class StockController extends Controller
{
    private const GOL_PER_BAHAR = 100;

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
        $stats = $this->calculateStockStats($stock);
        $alerts = $this->getAlerts($stock);

        return view('Stock::admin_stock_info', compact('stock', 'stats', 'alerts'));
    }

    private function getAlerts($stock)
    {
        $alerts = [];

        if (!$stock) {
            return $alerts;
        }

        $auctions = Auction::whereIn('status', ['scheduled', 'running'])->sum('shares_count');
        $availableAfterAuctions = ($stock->available_shares ?? 0) - $auctions;

        if ($availableAfterAuctions < ($stock->available_shares ?? 0) * 0.1) {
            $alerts[] = [
                'type' => 'warning',
                'title' => 'هشدار کمبود سهام قابل عرضه',
                'message' => 'تعداد سهام قابل عرضه باقی‌مانده کمتر از ۱۰٪ است. لطفاً موجودی را بررسی کنید.',
                'icon' => 'fa-exclamation-triangle'
            ];
        }

        $endingAuctions = Auction::where('status', 'running')
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

        $startingAuctions = Auction::where('status', 'scheduled')
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

        $pendingBids = Bid::where('status', 'active')
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
        $holdings = Holding::where('stock_id', $stock->id)->get();

        $totalAuctions = $auctions->count();
        $activeAuctions = $auctions->where('status', 'running')->count();
        $completedAuctions = $auctions->whereIn('status', ['settled', 'completed'])->count();
        $scheduledAuctions = $auctions->where('status', 'scheduled')->count();
        $canceledAuctions = $auctions->whereIn('status', ['canceled', 'cancelled'])->count();

        $totalBids = $bids->count();
        $activeBids = $bids->where('status', 'active')->count();
        $totalVolume = $bids->sum('quantity');

        $totalCapital = $bids->sum(function($bid) {
            return ($bid->price ?? 0) * ($bid->quantity ?? 0);
        });

        $prices = $bids->pluck('price')->filter()->values();
        $averagePrice = $prices->count() > 0 ? $prices->avg() : 0;
        $highestPrice = $prices->count() > 0 ? $prices->max() : 0;
        $lowestPrice = $prices->count() > 0 ? $prices->min() : 0;

        $totalInvestors = $holdings->unique('user_id')->count();
        $soldShares = $holdings->sum('shares_count');
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

    private function getChartData($auctions)
    {
        $labels = [];
        $volumes = [];
        $prices = [];
        $dates = [];

        for ($i = 11; $i >= 0; $i--) {
            $date = now()->subMonths($i);
            $monthLabel = \Morilog\Jalali\Jalalian::fromCarbon($date)->format('Y/m');

            $monthAuctions = $auctions->filter(function($auction) use ($date) {
                return $auction->start_time && $auction->start_time->format('Y-m') === $date->format('Y-m');
            });

            $monthVolume = $monthAuctions->sum('shares_count');
            $monthPrices = $monthAuctions->flatMap(function($auction) {
                return $auction->bids->pluck('price')->filter();
            });
            $monthAvgPrice = $monthPrices->count() > 0 ? $monthPrices->avg() : 0;

            $labels[] = $monthLabel;
            $volumes[] = $monthVolume;
            $prices[] = round($monthAvgPrice, 2);
            $dates[] = $date->format('Y-m-d');
        }

        return [
            'labels' => $labels,
            'volumes' => $volumes,
            'prices' => $prices,
            'dates' => $dates,
        ];
    }

    public function adminCreate()
    {
        $stock = Stock::first();
        return view('stock.admin_stock_create', compact('stock'));
    }

    public function adminStore(Request $request)
    {
        $data = $request->validate([
            'startup_valuation_bahar' => ['required', 'regex:/^\d+(?:\.\d{1,2})?$/'],
            'total_shares' => 'required|integer|min:1',
            'available_shares' => 'nullable|integer|min:0',
            'info' => 'nullable|string',
        ]);

        $valuationGol = $this->baharToGol((string) $data['startup_valuation_bahar']);
        $totalShares = (int) $data['total_shares'];
        if ($valuationGol <= 0 || $valuationGol % $totalShares !== 0) {
            throw ValidationException::withMessages([
                'startup_valuation_bahar' => 'ارزش‌گذاری باید پس از تبدیل دقیق به گل، بر تعداد کل سهام بخش‌پذیر باشد.',
            ]);
        }

        $baseSharePriceGol = intdiv($valuationGol, $totalShares);
        $stock = Stock::first();
        $oldBaseGol = (int) ($stock?->base_share_price_gol ?? 0);
        $attributes = [
            'issuer_type' => 'earthcoop',
            'startup_valuation_gol' => $valuationGol,
            'base_share_price_gol' => $baseSharePriceGol,
            'total_shares' => $totalShares,
            'available_shares' => $data['available_shares'] ?? $totalShares,
            'info' => $data['info'] ?? null,
            // Transitional mirror only. Canonical UI and settlement never use these decimal columns as authority.
            'startup_valuation' => $valuationGol / self::GOL_PER_BAHAR,
            'base_share_price' => $baseSharePriceGol / self::GOL_PER_BAHAR,
        ];

        $stock = Stock::updateOrCreate(['id' => $stock?->id], $attributes);

        if ($oldBaseGol !== $baseSharePriceGol && class_exists(\App\Modules\Stock\Events\StockPriceChanged::class)) {
            try {
                event(new \App\Modules\Stock\Events\StockPriceChanged($stock, $oldBaseGol, $baseSharePriceGol));
            } catch (\Throwable $e) {
                // Price event is non-authoritative for canonical persistence; keep admin save deterministic.
            }
        }

        return redirect()->route('admin.stock.index')->with('success', 'اطلاعات سهام به‌صورت canonical و بر مبنای گل ذخیره شد.');
    }

    public function adminGift()
    {
        $stock = Stock::first();
        return view('Stock.Views.admin_stock_gift', compact('stock'));
    }

    public function adminGiftStore(Request $request)
    {
        $data = $request->validate([
            'user_id' => 'required|exists:users,id',
            'shares_count' => 'required|integer|min:1',
        ]);

        $stock = Stock::firstOrFail();
        if (($stock->available_shares ?? 0) < $data['shares_count']) {
            return back()->withErrors(['shares_count' => 'سهام کافی موجود نیست.']);
        }

        $holding = Holding::firstOrCreate(
            ['user_id' => $data['user_id'], 'stock_id' => $stock->id],
            ['shares_count' => 0]
        );
        $holding->shares_count += $data['shares_count'];
        $holding->save();

        $stock->available_shares -= $data['shares_count'];
        $stock->save();

        StockTransaction::create([
            'user_id' => $data['user_id'],
            'stock_id' => $stock->id,
            'type' => 'gift',
            'shares_count' => $data['shares_count'],
            'price' => 0,
            'total_amount' => 0,
        ]);

        return redirect()->route('admin.stock.index')->with('success', 'سهام هدیه داده شد.');
    }

    public function adminShareholders()
    {
        $stock = Stock::first();
        $shareholders = collect();
        if ($stock) {
            $shareholders = Holding::with('user')
                ->where('stock_id', $stock->id)
                ->where('shares_count', '>', 0)
                ->orderByDesc('shares_count')
                ->get();
        }
        return view('Stock.Views.admin_stock_shareholders', compact('stock', 'shareholders'));
    }

    private function baharToGol(string $value): int
    {
        $parts = explode('.', $value, 2);
        $whole = (int) $parts[0];
        $fraction = str_pad($parts[1] ?? '', 2, '0');
        return ($whole * self::GOL_PER_BAHAR) + (int) substr($fraction, 0, 2);
    }

    // Legacy and public endpoints preserved below for compatibility.
    public function show($id) { $stock = Stock::findOrFail($id); return view('Stock.Views.stock_info', compact('stock')); }
    public function edit($id) { $stock = Stock::findOrFail($id); return view('Stock.Views.stock_create', compact('stock')); }
    public function update(Request $request,$id) { $stock=Stock::findOrFail($id); $stock->update($request->only(['startup_valuation','total_shares','available_shares','base_share_price','info'])); return redirect()->route('stock.index'); }
    public function destroy($id) { Stock::findOrFail($id)->delete(); return redirect()->route('stock.index'); }
    public function book() { return redirect()->route('stock.book'); }
    public function wallet() { $wallet = app(WalletService::class)->getOrCreateWallet(Auth::id()); return view('Stock.Views.wallet', compact('wallet')); }
}
