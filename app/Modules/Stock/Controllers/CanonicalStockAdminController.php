<?php

namespace App\Modules\Stock\Controllers;

use App\Modules\Stock\Models\Auction;
use App\Modules\Stock\Models\Bid;
use App\Modules\Stock\Models\Stock;

final class CanonicalStockAdminController extends StockController
{
    public function adminIndex()
    {
        $stock = Stock::first();

        $stats = [
            'total_auctions' => Auction::query()->count(),
            'active_auctions' => Auction::query()->where('status', 'running')->count(),
            'scheduled_auctions' => Auction::query()->where('status', 'scheduled')->count(),
            'settled_auctions' => Auction::query()->where('status', 'settled')->count(),
        ];

        $alerts = $this->canonicalAlerts($stock);

        return view('Stock::admin_stock_info', compact('stock', 'stats', 'alerts'));
    }

    private function canonicalAlerts(?Stock $stock): array
    {
        if (! $stock) {
            return [];
        }

        $alerts = [];
        $committedShares = (int) Auction::query()
            ->whereIn('status', ['scheduled', 'running'])
            ->sum('shares_count');
        $availableAfterAuctions = (int) ($stock->available_shares ?? 0) - $committedShares;

        if ($availableAfterAuctions < (int) (($stock->available_shares ?? 0) * 0.1)) {
            $alerts[] = [
                'type' => 'warning',
                'title' => 'هشدار کمبود سهام قابل عرضه',
                'message' => 'تعداد سهام خزانه باقی‌مانده پس از تعهدهای باز کمتر از ۱۰٪ موجودی فعلی است. لطفاً عرضه‌های باز را بررسی کنید.',
                'icon' => 'fa-exclamation-triangle',
            ];
        }

        $endingAuctions = Auction::query()
            ->where('status', 'running')
            ->where('ends_at', '<=', now()->addHours(24))
            ->where('ends_at', '>', now())
            ->count();
        if ($endingAuctions > 0) {
            $alerts[] = [
                'type' => 'info',
                'title' => 'حراج‌های در حال پایان',
                'message' => "{$endingAuctions} حراج در ۲۴ ساعت آینده به پایان می‌رسند.",
                'icon' => 'fa-clock',
            ];
        }

        $startingAuctions = Auction::query()
            ->where('status', 'scheduled')
            ->where('start_time', '<=', now()->addHours(24))
            ->where('start_time', '>', now())
            ->count();
        if ($startingAuctions > 0) {
            $alerts[] = [
                'type' => 'success',
                'title' => 'حراج‌های در حال شروع',
                'message' => "{$startingAuctions} حراج در ۲۴ ساعت آینده شروع می‌شوند.",
                'icon' => 'fa-play-circle',
            ];
        }

        $pendingBids = Bid::query()
            ->where('status', 'active')
            ->whereHas('auction', static fn ($query) => $query->where('status', 'running'))
            ->count();
        if ($pendingBids > 100) {
            $alerts[] = [
                'type' => 'warning',
                'title' => 'تعداد بالای پیشنهادها',
                'message' => "{$pendingBids} پیشنهاد فعال در حراج‌های جاری وجود دارد. لطفاً بررسی کنید.",
                'icon' => 'fa-hand-pointer',
            ];
        }

        return $alerts;
    }
}
