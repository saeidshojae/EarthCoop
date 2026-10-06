<?php

namespace App\Http\Controllers\Admin;

use App\Helpers\BaharMoney;
use App\Http\Controllers\Controller;
use App\Modules\NajmBahar\Models\Account;
use App\Modules\NajmBahar\Models\SubAccount;
use App\Modules\NajmBahar\Models\Transaction;
use App\Temporal\Context\TemporalContextResolver;
use App\Temporal\Contracts\TemporalService;
use App\Temporal\ValueObjects\LocalDate;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class NajmBaharAnalyticsController extends Controller
{
    public function __construct(
        private readonly TemporalService $temporal,
        private readonly TemporalContextResolver $temporalContexts,
    ) {
    }

    /**
     * نمایش گزارش‌های تحلیلی
     */
    public function index(Request $request)
    {
        $context = $this->temporalContexts->forUser($request->user());
        $localNow = now()->setTimezone($context->timezone());
        $defaultFrom = LocalDate::fromCanonical($localNow->copy()->subMonth()->format('Y-m-d'));
        $defaultTo = LocalDate::fromCanonical($localNow->format('Y-m-d'));

        try {
            $fromDate = $request->filled('date_from')
                ? $this->temporal->parseDate((string) $request->input('date_from'), $context)
                : $defaultFrom;
            $toDate = $request->filled('date_to')
                ? $this->temporal->parseDate((string) $request->input('date_to'), $context)
                : $defaultTo;
        } catch (\Throwable) {
            $fromDate = $defaultFrom;
            $toDate = $defaultTo;
        }

        if ($fromDate->toCanonical() > $toDate->toCanonical()) {
            [$fromDate, $toDate] = [$toDate, $fromDate];
        }

        $rangeStart = $this->temporal->startOfDay($fromDate, $context);
        $rangeEnd = $this->temporal->endOfDay($toDate, $context);
        $dateFrom = $this->temporal->date($fromDate, $context, 'short');
        $dateTo = $this->temporal->date($toDate, $context, 'short');

        $totalTransactions = Transaction::whereBetween('created_at', [$rangeStart, $rangeEnd])
            ->where('status', 'completed')
            ->count();

        $totalVolume = Transaction::whereBetween('created_at', [$rangeStart, $rangeEnd])
            ->where('status', 'completed')
            ->sum('amount');

        $avgTransactionAmount = $totalTransactions > 0 ? (int) round($totalVolume / $totalTransactions) : 0;

        // Daily aggregation remains canonical UTC in v1; labels are presentation work.
        // The selected range itself is resolved from the user's local civil days.
        $dailyStats = Transaction::select(
                DB::raw('DATE(created_at) as date'),
                DB::raw('COUNT(*) as count'),
                DB::raw('SUM(amount) as volume'),
                DB::raw('AVG(amount) as avg_amount')
            )
            ->whereBetween('created_at', [$rangeStart, $rangeEnd])
            ->where('status', 'completed')
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        $dailyStats->each(function ($stat) use ($context) {
            $stat->date_label = $this->temporal->date(
                LocalDate::fromCanonical((string) $stat->date),
                $context,
                'month-day',
            );
        });

        $typeDistribution = Transaction::select(
                'type',
                DB::raw('COUNT(*) as count'),
                DB::raw('SUM(amount) as volume')
            )
            ->whereBetween('created_at', [$rangeStart, $rangeEnd])
            ->where('status', 'completed')
            ->groupBy('type')
            ->get();

        $largeThreshold = BaharMoney::toGolFromBahar(100000);
        $largeTransactions = Transaction::whereBetween('created_at', [$rangeStart, $rangeEnd])
            ->where('status', 'completed')
            ->where('amount', '>=', $largeThreshold)
            ->orderBy('amount', 'desc')
            ->limit(20)
            ->get();

        $activeAccounts = Account::where(function ($query) use ($rangeStart, $rangeEnd) {
            $query->whereHas('outgoingTransactions', function ($q) use ($rangeStart, $rangeEnd) {
                $q->whereBetween('created_at', [$rangeStart, $rangeEnd])
                    ->where('status', 'completed');
            })->orWhereHas('incomingTransactions', function ($q) use ($rangeStart, $rangeEnd) {
                $q->whereBetween('created_at', [$rangeStart, $rangeEnd])
                    ->where('status', 'completed');
            });
        })
        ->withCount([
            'outgoingTransactions' => function ($query) use ($rangeStart, $rangeEnd) {
                $query->whereBetween('created_at', [$rangeStart, $rangeEnd])
                    ->where('status', 'completed');
            },
            'incomingTransactions' => function ($query) use ($rangeStart, $rangeEnd) {
                $query->whereBetween('created_at', [$rangeStart, $rangeEnd])
                    ->where('status', 'completed');
            },
        ])
        ->get()
        ->map(function ($account) {
            $account->transactions_count = $account->outgoing_transactions_count + $account->incoming_transactions_count;
            return $account;
        })
        ->sortByDesc('transactions_count')
        ->take(20)
        ->values();

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
