<?php

namespace App\Modules\Stock\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Stock\Models\Auction;
use App\Modules\Stock\Models\Holding;
use App\Temporal\Context\TemporalContextResolver;
use App\Temporal\Contracts\TemporalService;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;

class StockReportController extends Controller
{
    public function __construct(
        private readonly TemporalService $temporal,
        private readonly TemporalContextResolver $temporalContexts,
    ) {
    }

    public function auctionPerformance(Request $request)
    {
        [$dateFrom, $dateTo] = $this->dateRange($request);

        $auctions = Auction::whereBetween('start_time', [$dateFrom, $dateTo])
            ->with(['bids', 'stock'])
            ->get();

        $allBids = $auctions->flatMap(fn ($auction) => $auction->bids);
        $wonBids = $allBids->where('status', 'won');
        $pricedBids = $allBids->filter(fn ($bid) => (int) ($bid->price_gol ?? 0) > 0);

        $stats = [
            'total_auctions' => $auctions->count(),
            'completed_auctions' => $auctions->whereIn('status', ['settled', 'completed'])->count(),
            'canceled_auctions' => $auctions->whereIn('status', ['canceled', 'cancelled'])->count(),
            'total_shares_offered' => $auctions->sum('shares_count'),
            'total_shares_sold' => $wonBids->sum('quantity'),
            'total_bids' => $allBids->count(),
            'total_volume' => $allBids->sum('quantity'),
            'total_capital_gol' => $wonBids->sum(fn ($bid) => $this->bidTotalGol($bid)),
            'average_price_gol' => $pricedBids->avg('price_gol') ?? 0,
        ];

        return view('Stock::admin_reports.auction_performance', compact('auctions', 'stats', 'dateFrom', 'dateTo'));
    }

    public function investors(Request $request)
    {
        $allHoldings = Holding::with(['user', 'stock'])->get();

        $grouped = $allHoldings->groupBy(fn ($holding) => $holding->user_id . '_' . $holding->stock_id)
            ->map(function ($group) {
                $first = $group->first();
                $shares = (int) $group->sum('quantity');
                $baseSharePriceGol = (int) ($first->stock->base_share_price_gol ?? 0);

                return (object) [
                    'user_id' => $first->user_id,
                    'stock_id' => $first->stock_id,
                    'user' => $first->user,
                    'stock' => $first->stock,
                    'total_shares' => $shares,
                    'base_asset_value_gol' => $shares * $baseSharePriceGol,
                ];
            })
            ->sortByDesc('base_asset_value_gol')
            ->values();

        $page = (int) $request->get('page', 1);
        $perPage = 50;
        $investors = new LengthAwarePaginator(
            $grouped->slice(($page - 1) * $perPage),
            $grouped->count(),
            $perPage,
            $page,
            ['path' => $request->url(), 'query' => $request->query()]
        );

        $totalInvestors = Holding::distinct('user_id')->count('user_id');
        $totalAssetValueGol = $allHoldings->sum(fn ($holding) =>
            (int) $holding->quantity * (int) ($holding->stock->base_share_price_gol ?? 0)
        );

        return view('Stock::admin_reports.investors', compact('investors', 'totalInvestors', 'totalAssetValueGol'));
    }

    public function financial(Request $request)
    {
        [$dateFrom, $dateTo] = $this->dateRange($request);

        $auctions = Auction::whereBetween('start_time', [$dateFrom, $dateTo])
            ->whereIn('status', ['settled', 'completed'])
            ->with('bids')
            ->get();

        $wonBids = $auctions->flatMap(fn ($auction) => $auction->bids->where('status', 'won'));
        $pricedWonBids = $wonBids->filter(fn ($bid) => (int) ($bid->price_gol ?? 0) > 0);

        $sales = [
            'total_sales_gol' => $wonBids->sum(fn ($bid) => $this->bidTotalGol($bid)),
            'total_shares_sold' => $wonBids->sum('quantity'),
            'average_price_gol' => $pricedWonBids->avg('price_gol') ?? 0,
            'total_transactions' => $wonBids->count(),
        ];

        return view('Stock::admin_reports.financial', compact('sales', 'auctions', 'dateFrom', 'dateTo'));
    }

    public function exportAuctionPerformance(Request $request)
    {
        [$dateFrom, $dateTo] = $this->dateRange($request);
        $auctions = Auction::whereBetween('start_time', [$dateFrom, $dateTo])->with(['bids', 'stock'])->get();
        $context = $this->temporalContexts->defaultContext();

        return response()->stream(function () use ($auctions, $context) {
            $file = fopen('php://output', 'w');
            fprintf($file, chr(0xEF) . chr(0xBB) . chr(0xBF));
            fputcsv($file, [
                'شناسه حراج', 'تعداد سهام', 'قیمت پایه (گل)', 'معادل پایه (بهار)', 'وضعیت', 'نوع',
                'تعداد پیشنهادها', 'بالاترین پیشنهاد (گل)', 'میانگین قیمت (گل)', 'حجم پیشنهادها', 'تاریخ شروع', 'تاریخ پایان',
            ]);

            foreach ($auctions as $auction) {
                $bids = $auction->bids;
                $canonicalBids = $bids->filter(fn ($bid) => (int) ($bid->price_gol ?? 0) > 0);
                $baseGol = (int) ($auction->base_price_gol ?? 0);
                fputcsv($file, [
                    $auction->id,
                    $auction->shares_count,
                    $baseGol,
                    $baseGol / 100,
                    $this->statusLabel($auction->status),
                    $this->typeLabel($auction->type),
                    $bids->count(),
                    $canonicalBids->max('price_gol') ?? 0,
                    round((float) ($canonicalBids->avg('price_gol') ?? 0), 2),
                    $bids->sum('quantity'),
                    $auction->start_time ? $this->temporal->dateTime($auction->start_time, $context, 'short') : '',
                    $auction->ends_at ? $this->temporal->dateTime($auction->ends_at, $context, 'short') : '',
                ]);
            }
            fclose($file);
        }, 200, $this->csvHeaders('auction_performance'));
    }

    public function exportInvestors()
    {
        $allHoldings = Holding::with(['user', 'stock'])->get();
        $investors = $allHoldings->groupBy(fn ($holding) => $holding->user_id . '_' . $holding->stock_id)
            ->map(function ($group) {
                $first = $group->first();
                $shares = (int) $group->sum('quantity');
                $baseSharePriceGol = (int) ($first->stock->base_share_price_gol ?? 0);
                return (object) [
                    'user_id' => $first->user_id,
                    'stock_id' => $first->stock_id,
                    'user' => $first->user,
                    'total_shares' => $shares,
                    'base_asset_value_gol' => $shares * $baseSharePriceGol,
                ];
            })
            ->sortByDesc('base_asset_value_gol')
            ->values();

        return response()->stream(function () use ($investors) {
            $file = fopen('php://output', 'w');
            fprintf($file, chr(0xEF) . chr(0xBB) . chr(0xBF));
            fputcsv($file, ['شناسه کاربر', 'نام', 'ایمیل', 'شناسه سهام', 'تعداد کل سهام', 'ارزش پایه دارایی (گل)', 'معادل (بهار)']);

            foreach ($investors as $investor) {
                $user = $investor->user;
                fputcsv($file, [
                    $user->id ?? '',
                    trim(($user->first_name ?? '') . ' ' . ($user->last_name ?? '')),
                    $user->email ?? '',
                    $investor->stock_id ?? '',
                    $investor->total_shares ?? 0,
                    $investor->base_asset_value_gol ?? 0,
                    ($investor->base_asset_value_gol ?? 0) / 100,
                ]);
            }
            fclose($file);
        }, 200, $this->csvHeaders('investors_report'));
    }

    public function exportFinancial(Request $request)
    {
        [$dateFrom, $dateTo] = $this->dateRange($request);
        $auctions = Auction::whereBetween('start_time', [$dateFrom, $dateTo])
            ->whereIn('status', ['settled', 'completed'])
            ->with('bids')
            ->get();
        $context = $this->temporalContexts->defaultContext();

        return response()->stream(function () use ($auctions, $context) {
            $file = fopen('php://output', 'w');
            fprintf($file, chr(0xEF) . chr(0xBB) . chr(0xBF));
            fputcsv($file, [
                'شناسه حراج', 'تعداد سهام فروخته‌شده', 'ارزش فروش (گل)', 'معادل فروش (بهار)',
                'تعداد تراکنش‌ها', 'میانگین قیمت فروش (گل)', 'تاریخ',
            ]);

            foreach ($auctions as $auction) {
                $wonBids = $auction->bids->where('status', 'won');
                $revenueGol = $wonBids->sum(fn ($bid) => $this->bidTotalGol($bid));
                $pricedWonBids = $wonBids->filter(fn ($bid) => (int) ($bid->price_gol ?? 0) > 0);
                fputcsv($file, [
                    $auction->id,
                    $wonBids->sum('quantity'),
                    $revenueGol,
                    $revenueGol / 100,
                    $wonBids->count(),
                    round((float) ($pricedWonBids->avg('price_gol') ?? 0), 2),
                    $auction->start_time ? $this->temporal->date($auction->start_time, $context, 'short') : '',
                ]);
            }
            fclose($file);
        }, 200, $this->csvHeaders('financial_report'));
    }

    private function dateRange(Request $request): array
    {
        $context = $this->temporalContexts->defaultContext();
        $dateFrom = $request->filled('date_from')
            ? $this->temporal->startOfDay($this->temporal->parseDate((string) $request->input('date_from'), $context), $context)
            : now()->subMonths(6);
        $dateTo = $request->filled('date_to')
            ? $this->temporal->endOfDay($this->temporal->parseDate((string) $request->input('date_to'), $context), $context)
            : now();

        return [$dateFrom, $dateTo];
    }

    private function bidTotalGol($bid): int
    {
        $priceGol = (int) ($bid->price_gol ?? 0);
        $quantity = (int) ($bid->quantity ?? 0);
        return $priceGol > 0 && $quantity > 0 ? $priceGol * $quantity : 0;
    }

    private function statusLabel(?string $status): string
    {
        return [
            'scheduled' => 'برنامه‌ریزی‌شده', 'running' => 'در حال اجرا', 'settling' => 'در حال تسویه',
            'settled' => 'تسویه‌شده', 'completed' => 'تکمیل‌شده', 'canceled' => 'لغوشده', 'cancelled' => 'لغوشده',
        ][$status] ?? 'نامشخص';
    }

    private function typeLabel(?string $type): string
    {
        return [
            'single_winner' => 'تک‌برنده', 'uniform_price' => 'قیمت یکسان', 'pay_as_bid' => 'پرداخت به قیمت پیشنهادی',
        ][$type] ?? 'نامشخص';
    }

    private function csvHeaders(string $prefix): array
    {
        return [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . $prefix . '_' . gmdate('Y-m-d_H-i-s') . '.csv"',
        ];
    }
}
