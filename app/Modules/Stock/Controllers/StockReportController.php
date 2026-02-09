<?php

namespace App\Modules\Stock\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Modules\Stock\Models\Auction;
use App\Modules\Stock\Models\Bid;
use App\Modules\Stock\Models\Stock;
use App\Modules\Stock\Models\Holding;
use Carbon\Carbon;
use Morilog\Jalali\Jalalian;

class StockReportController extends Controller
{
    // گزارش عملکرد حراج‌ها
    public function auctionPerformance(Request $request)
    {
        $dateFrom = $request->input('date_from') ? 
            (strpos($request->input('date_from'), '/') !== false ? 
                Jalalian::fromFormat('Y/m/d', $request->input('date_from'))->toCarbon() : 
                Carbon::parse($request->input('date_from'))
            ) : now()->subMonths(6);
            
        $dateTo = $request->input('date_to') ? 
            (strpos($request->input('date_to'), '/') !== false ? 
                Jalalian::fromFormat('Y/m/d', $request->input('date_to'))->toCarbon()->endOfDay() : 
                Carbon::parse($request->input('date_to'))->endOfDay()
            ) : now();
        
        $auctions = Auction::whereBetween('start_time', [$dateFrom, $dateTo])
            ->with(['bids', 'stock'])
            ->get();
        
        // محاسبه آمار
        $stats = [
            'total_auctions' => $auctions->count(),
            'completed_auctions' => $auctions->whereIn('status', ['settled', 'completed'])->count(),
            'canceled_auctions' => $auctions->whereIn('status', ['canceled', 'cancelled'])->count(),
            'total_shares_offered' => $auctions->sum('shares_count'),
            'total_shares_sold' => $auctions->whereIn('status', ['settled', 'completed'])->sum('shares_count'),
            'total_bids' => $auctions->sum(fn($a) => $a->bids->count()),
            'total_volume' => $auctions->sum(fn($a) => $a->bids->sum('quantity')),
            'total_capital' => $auctions->sum(fn($a) => $a->bids->sum(fn($b) => ($b->price ?? 0) * ($b->quantity ?? 0))),
            'average_price' => $auctions->flatMap(fn($a) => $a->bids)->pluck('price')->filter()->avg() ?? 0,
        ];
        
        return view('Stock::admin_reports.auction_performance', compact('auctions', 'stats', 'dateFrom', 'dateTo'));
    }
    
    // گزارش سرمایه‌گذاران
    public function investors(Request $request)
    {
        // ابتدا تمام holdings را با relationships بگیریم
        $allHoldings = Holding::with(['user', 'stock'])->get();
        
        // گروه‌بندی و محاسبه
        $grouped = $allHoldings->groupBy(function($holding) {
            return $holding->user_id . '_' . $holding->stock_id;
        })->map(function($group) {
            $first = $group->first();
            return (object)[
                'user_id' => $first->user_id,
                'stock_id' => $first->stock_id,
                'user' => $first->user,
                'stock' => $first->stock,
                'total_shares' => $group->sum('quantity'),
                'total_investment' => $group->sum(function($h) {
                    return $h->quantity * ($h->stock->base_share_price ?? 0);
                }),
            ];
        })->sortByDesc('total_investment')->values();
        
        // Pagination دستی
        $page = $request->get('page', 1);
        $perPage = 50;
        $offset = ($page - 1) * $perPage;
        $investors = new \Illuminate\Pagination\LengthAwarePaginator(
            $grouped->slice($offset, $perPage),
            $grouped->count(),
            $perPage,
            $page,
            ['path' => $request->url(), 'query' => $request->query()]
        );
        
        $totalInvestors = Holding::distinct('user_id')->count('user_id');
        $totalInvestment = $allHoldings->sum(function($holding) {
            return $holding->quantity * ($holding->stock->base_share_price ?? 0);
        });
        
        return view('Stock::admin_reports.investors', compact('investors', 'totalInvestors', 'totalInvestment'));
    }
    
    // گزارش مالی
    public function financial(Request $request)
    {
        $dateFrom = $request->input('date_from') ? 
            (strpos($request->input('date_from'), '/') !== false ? 
                Jalalian::fromFormat('Y/m/d', $request->input('date_from'))->toCarbon() : 
                Carbon::parse($request->input('date_from'))
            ) : now()->subMonths(6);
            
        $dateTo = $request->input('date_to') ? 
            (strpos($request->input('date_to'), '/') !== false ? 
                Jalalian::fromFormat('Y/m/d', $request->input('date_to'))->toCarbon()->endOfDay() : 
                Carbon::parse($request->input('date_to'))->endOfDay()
            ) : now();
        
        $auctions = Auction::whereBetween('start_time', [$dateFrom, $dateTo])
            ->whereIn('status', ['settled', 'completed'])
            ->with('bids')
            ->get();
        
        // محاسبه درآمدها
        $revenue = $auctions->sum(function($auction) {
            return $auction->bids->where('status', 'won')->sum(function($bid) {
                return ($bid->price ?? 0) * ($bid->quantity ?? 0);
            });
        });
        
        // آمار فروش
        $sales = [
            'total_sales' => $revenue,
            'total_shares_sold' => $auctions->sum('shares_count'),
            'average_price' => $auctions->flatMap(fn($a) => $a->bids->where('status', 'won'))->pluck('price')->filter()->avg() ?? 0,
            'total_transactions' => $auctions->sum(fn($a) => $a->bids->where('status', 'won')->count()),
        ];
        
        return view('Stock::admin_reports.financial', compact('sales', 'auctions', 'dateFrom', 'dateTo'));
    }
    
    // Export گزارش عملکرد حراج‌ها
    public function exportAuctionPerformance(Request $request)
    {
        $dateFrom = $request->input('date_from') ? 
            (strpos($request->input('date_from'), '/') !== false ? 
                Jalalian::fromFormat('Y/m/d', $request->input('date_from'))->toCarbon() : 
                Carbon::parse($request->input('date_from'))
            ) : now()->subMonths(6);
            
        $dateTo = $request->input('date_to') ? 
            (strpos($request->input('date_to'), '/') !== false ? 
                Jalalian::fromFormat('Y/m/d', $request->input('date_to'))->toCarbon()->endOfDay() : 
                Carbon::parse($request->input('date_to'))->endOfDay()
            ) : now();
        
        $auctions = Auction::whereBetween('start_time', [$dateFrom, $dateTo])
            ->with(['bids', 'stock'])
            ->get();
        
        $filename = 'auction_performance_' . date('Y-m-d_H-i-s') . '.csv';
        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ];
        
        $callback = function() use ($auctions) {
            $file = fopen('php://output', 'w');
            fprintf($file, chr(0xEF).chr(0xBB).chr(0xBF)); // BOM for UTF-8
            
            fputcsv($file, [
                'شناسه حراج', 'تعداد سهام', 'قیمت پایه', 'وضعیت', 'نوع', 
                'تعداد پیشنهادها', 'بالاترین پیشنهاد', 'میانگین قیمت', 
                'حجم پیشنهادها', 'تاریخ شروع', 'تاریخ پایان'
            ]);
            
            foreach ($auctions as $auction) {
                $bids = $auction->bids;
                $highestBid = $bids->max('price') ?? 0;
                $avgPrice = $bids->pluck('price')->filter()->avg() ?? 0;
                
                fputcsv($file, [
                    $auction->id,
                    $auction->shares_count,
                    $auction->base_price,
                    $auction->status,
                    $auction->type,
                    $bids->count(),
                    $highestBid,
                    round($avgPrice, 2),
                    $bids->sum('quantity'),
                    $auction->start_time ? Jalalian::fromCarbon($auction->start_time)->format('Y/m/d H:i') : '',
                    $auction->ends_at ? Jalalian::fromCarbon($auction->ends_at)->format('Y/m/d H:i') : '',
                ]);
            }
            
            fclose($file);
        };
        
        return response()->stream($callback, 200, $headers);
    }
    
    // Export گزارش سرمایه‌گذاران
    public function exportInvestors()
    {
        // ابتدا تمام holdings را با relationships بگیریم
        $allHoldings = Holding::with(['user', 'stock'])->get();
        
        // گروه‌بندی و محاسبه
        $investors = $allHoldings->groupBy(function($holding) {
            return $holding->user_id . '_' . $holding->stock_id;
        })->map(function($group) {
            $first = $group->first();
            return (object)[
                'user_id' => $first->user_id,
                'stock_id' => $first->stock_id,
                'user' => $first->user,
                'stock' => $first->stock,
                'total_shares' => $group->sum('quantity'),
                'total_investment' => $group->sum(function($h) {
                    return $h->quantity * ($h->stock->base_share_price ?? 0);
                }),
            ];
        })->sortByDesc('total_investment')->values();
        
        $filename = 'investors_report_' . date('Y-m-d_H-i-s') . '.csv';
        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ];
        
        $callback = function() use ($investors) {
            $file = fopen('php://output', 'w');
            fprintf($file, chr(0xEF).chr(0xBB).chr(0xBF)); // BOM for UTF-8
            
            fputcsv($file, [
                'شناسه کاربر', 'نام', 'ایمیل', 'شناسه سهام', 
                'تعداد کل سهام', 'کل سرمایه‌گذاری (تومان)'
            ]);
            
            foreach ($investors as $investor) {
                $user = $investor->user;
                fputcsv($file, [
                    $user->id ?? '',
                    ($user->first_name ?? '') . ' ' . ($user->last_name ?? ''),
                    $user->email ?? '',
                    $investor->stock_id ?? '',
                    $investor->total_shares ?? 0,
                    ($investor->total_investment ?? 0),
                ]);
            }
            
            fclose($file);
        };
        
        return response()->stream($callback, 200, $headers);
    }
    
    // Export گزارش مالی
    public function exportFinancial(Request $request)
    {
        $dateFrom = $request->input('date_from') ? 
            (strpos($request->input('date_from'), '/') !== false ? 
                Jalalian::fromFormat('Y/m/d', $request->input('date_from'))->toCarbon() : 
                Carbon::parse($request->input('date_from'))
            ) : now()->subMonths(6);
            
        $dateTo = $request->input('date_to') ? 
            (strpos($request->input('date_to'), '/') !== false ? 
                Jalalian::fromFormat('Y/m/d', $request->input('date_to'))->toCarbon()->endOfDay() : 
                Carbon::parse($request->input('date_to'))->endOfDay()
            ) : now();
        
        $auctions = Auction::whereBetween('start_time', [$dateFrom, $dateTo])
            ->whereIn('status', ['settled', 'completed'])
            ->with('bids')
            ->get();
        
        $filename = 'financial_report_' . date('Y-m-d_H-i-s') . '.csv';
        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ];
        
        $callback = function() use ($auctions) {
            $file = fopen('php://output', 'w');
            fprintf($file, chr(0xEF).chr(0xBB).chr(0xBF)); // BOM for UTF-8
            
            fputcsv($file, [
                'شناسه حراج', 'تعداد سهام فروخته شده', 'کل درآمد (تومان)', 
                'تعداد تراکنش‌ها', 'میانگین قیمت فروش (تومان)', 'تاریخ'
            ]);
            
            foreach ($auctions as $auction) {
                $wonBids = $auction->bids->where('status', 'won');
                $revenue = $wonBids->sum(function($bid) {
                    return ($bid->price ?? 0) * ($bid->quantity ?? 0);
                });
                $avgPrice = $wonBids->pluck('price')->filter()->avg() ?? 0;
                
                fputcsv($file, [
                    $auction->id,
                    $auction->shares_count,
                    $revenue,
                    $wonBids->count(),
                    round($avgPrice, 2),
                    $auction->start_time ? Jalalian::fromCarbon($auction->start_time)->format('Y/m/d') : '',
                ]);
            }
            
            fclose($file);
        };
        
        return response()->stream($callback, 200, $headers);
    }
}

