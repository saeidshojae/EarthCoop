<?php

namespace App\Modules\Stock\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Stock\Models\Auction;
use App\Services\NotificationService;
use App\Temporal\Context\TemporalContextResolver;
use App\Temporal\Contracts\TemporalService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Log;

final class CanonicalAdminAuctionLifecycleController extends Controller
{
    public function __construct(
        private readonly TemporalService $temporal,
        private readonly TemporalContextResolver $temporalContexts,
        private readonly NotificationService $notifications,
    ) {
    }

    public function start(Auction $auction): RedirectResponse
    {
        if ($auction->status !== 'scheduled') {
            return back()->with('error', 'فقط حراج‌های برنامه‌ریزی شده قابل شروع هستند');
        }

        if ($auction->ends_at && $auction->ends_at->isPast()) {
            return back()->with('error', 'حراجی که زمان پایان آن گذشته است قابل شروع نیست');
        }

        $auction->update(['status' => 'running']);

        try {
            $users = User::whereHas('roles', function ($query): void {
                $query->where('slug', '!=', 'super-admin');
            })->orWhere('is_admin', false)->get();

            $context = $this->temporalContexts->defaultContext();
            $deadline = $auction->ends_at
                ? $this->temporal->dateTime($auction->ends_at, $context, 'short')
                : '—';

            $this->notifications->notifyMany(
                $users,
                'حراج جدید شروع شد',
                "حراج #{$auction->id} شروع شد. فرصت پیشنهاد دادن تا {$deadline} است.",
                route('auction.show', $auction),
                'success',
                ['auction_id' => $auction->id],
            );
        } catch (\Throwable $exception) {
            Log::warning('Failed to send auction start notification: '.$exception->getMessage());
        }

        return back()->with('success', 'حراج شروع شد');
    }
}
