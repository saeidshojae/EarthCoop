<?php

namespace App\Http\Controllers\Admin;

use App\Helpers\BaharMoney;
use App\Http\Controllers\Controller;
use App\Modules\NajmBahar\Models\Account;
use App\Modules\NajmBahar\Models\Transaction;
use App\Modules\NajmBahar\Models\SubAccount;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class NajmBaharAnalyticsController extends Controller
{
    /**
     * نمایش گزارش‌های تحلیلی
     */
    public function index(Request $request)
    {
        // فیلتر تاریخ
        $dateFrom = $request->input('date_from', Carbon::now()->subMonth()->format('Y-m-d'));
        $dateTo = $request->input('date_to', Carbon::now()->format('Y-m-d'));

        // آمار کلی در بازه زمانی
        $totalTransactions = Transaction::whereBetween('created_at', [
            Carbon::parse($dateFrom)->startOfDay(),
            Carbon::parse($dateTo)->endOfDay()
        ])->where('status', 'completed')->count();

        $totalVolume = Transaction::whereBetween('created_at', [
            Carbon::parse($dateFrom)->startOfDay(),
            Carbon::parse($dateTo)->endOfDay()
        ])->where('status', 'completed')->sum('amount');

        $avgTransactionAmount = $totalTransactions > 0 ? (int) round($totalVolume / $totalTransactions) : 0;

        // تراکنش‌های روزانه
        $dailyStats = Transaction::select(
                DB::raw('DATE(created_at) as date'),
                DB::raw('COUNT(*) as count'),
                DB::raw('SUM(amount) as volume'),
                DB::raw('AVG(amount) as avg_amount')
            )
            ->whereBetween('created_at', [
                Carbon::parse($dateFrom)->startOfDay(),
                Carbon::parse($dateTo)->endOfDay()
            ])
            ->where('status', 'completed')
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        // توزیع بر اساس نوع تراکنش
        $typeDistribution = Transaction::select(
                'type',
                DB::raw('COUNT(*) as count'),
                DB::raw('SUM(amount) as volume')
            )
            ->whereBetween('created_at', [
                Carbon::parse($dateFrom)->startOfDay(),
                Carbon::parse($dateTo)->endOfDay()
            ])
            ->where('status', 'completed')
            ->groupBy('type')
            ->get();

        // تراکنش‌های بزرگ (بالای 100,000 بهار)
        $largeThreshold = BaharMoney::toGolFromBahar(100000);
        $largeTransactions = Transaction::whereBetween('created_at', [
            Carbon::parse($dateFrom)->startOfDay(),
            Carbon::parse($dateTo)->endOfDay()
        ])
        ->where('status', 'completed')
        ->where('amount', '>=', $largeThreshold)
        ->orderBy('amount', 'desc')
        ->limit(20)
        ->get();

        // حساب‌های فعال (با تراکنش)
        $activeAccounts = Account::where(function($query) use ($dateFrom, $dateTo) {
            $query->whereHas('outgoingTransactions', function($q) use ($dateFrom, $dateTo) {
                $q->whereBetween('created_at', [
                    Carbon::parse($dateFrom)->startOfDay(),
                    Carbon::parse($dateTo)->endOfDay()
                ])->where('status', 'completed');
            })->orWhereHas('incomingTransactions', function($q) use ($dateFrom, $dateTo) {
                $q->whereBetween('created_at', [
                    Carbon::parse($dateFrom)->startOfDay(),
                    Carbon::parse($dateTo)->endOfDay()
                ])->where('status', 'completed');
            });
        })
        ->withCount([
            'outgoingTransactions' => function($query) use ($dateFrom, $dateTo) {
                $query->whereBetween('created_at', [
                    Carbon::parse($dateFrom)->startOfDay(),
                    Carbon::parse($dateTo)->endOfDay()
                ])->where('status', 'completed');
            },
            'incomingTransactions' => function($query) use ($dateFrom, $dateTo) {
                $query->whereBetween('created_at', [
                    Carbon::parse($dateFrom)->startOfDay(),
                    Carbon::parse($dateTo)->endOfDay()
                ])->where('status', 'completed');
            }
        ])
        ->get()
        ->map(function($account) {
            $account->transactions_count = $account->outgoing_transactions_count + $account->incoming_transactions_count;
            return $account;
        })
        ->sortByDesc('transactions_count')
        ->take(20)
        ->values();

        // آمار حساب‌های فرعی
        $subAccountStats = SubAccount::select(
                DB::raw('COUNT(*) as total'),
                DB::raw('SUM(balance) as total_balance'),
                DB::raw('AVG(balance) as avg_balance')
            )
            ->where('status', 1)
            ->first();

        return view('admin.najm-bahar.analytics', compact(
            'dateFrom',
            'dateTo',
            'totalTransactions',
            'totalVolume',
            'avgTransactionAmount',
            'dailyStats',
            'typeDistribution',
            'largeTransactions',
            'activeAccounts',
            'subAccountStats'
        ));
    }
}

