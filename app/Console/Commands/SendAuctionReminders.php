<?php

namespace App\Console\Commands;

use App\Modules\Stock\Models\Auction;
use App\Modules\Stock\Models\Bid;
use App\Events\AuctionReminder;
use App\Models\User;
use Illuminate\Console\Command;
use Carbon\Carbon;

class SendAuctionReminders extends Command
{
    protected $signature = 'auctions:send-reminders';
    protected $description = 'ارسال یادآوری برای حراج‌های در حال پایان';

    public function handle()
    {
        $this->info('ارسال یادآوری حراج‌ها...');

        // حراج‌های فعال که در حال پایان هستند
        $activeAuctions = Auction::where('status', 'running')
            ->where('ends_at', '>', now())
            ->where('ends_at', '<=', now()->addHours(24)) // فقط حراج‌هایی که تا 24 ساعت آینده تمام می‌شوند
            ->get();

        if ($activeAuctions->isEmpty()) {
            $this->info('هیچ حراج فعالی برای یادآوری یافت نشد.');
            return Command::SUCCESS;
        }

        $totalSent = 0;

        foreach ($activeAuctions as $auction) {
            $endsAt = Carbon::parse($auction->ends_at);
            $now = now();
            $hoursRemaining = $endsAt->diffInHours($now);
            $minutesRemaining = $endsAt->diffInMinutes($now) % 60;

            // فقط در زمان‌های مشخص یادآوری بفرست: 24 ساعت، 6 ساعت، 1 ساعت قبل
            $shouldSend = false;
            $timeRemaining = '';

            if ($hoursRemaining <= 1 && $hoursRemaining > 0) {
                $shouldSend = true;
                $timeRemaining = "{$hoursRemaining} ساعت و {$minutesRemaining} دقیقه";
            } elseif ($hoursRemaining <= 6 && $hoursRemaining > 5) {
                $shouldSend = true;
                $timeRemaining = "{$hoursRemaining} ساعت";
            } elseif ($hoursRemaining <= 24 && $hoursRemaining > 23) {
                $shouldSend = true;
                $timeRemaining = "{$hoursRemaining} ساعت";
            }

            if (!$shouldSend) {
                continue;
            }

            // دریافت کاربرانی که در این حراج پیشنهاد داده‌اند
            $bidders = Bid::where('auction_id', $auction->id)
                ->where('status', 'active')
                ->distinct('user_id')
                ->pluck('user_id')
                ->all();

            // همچنین به همه کاربران فعال که می‌توانند شرکت کنند
            // (می‌توانید این بخش را محدود کنید به کاربرانی که قبلاً در حراج‌ها شرکت کرده‌اند)
            $allUsers = User::where('is_admin', false)->pluck('id')->all();
            
            // ترکیب: بیدرها + همه کاربران (برای اطلاع‌رسانی عمومی)
            $recipientIds = array_unique(array_merge($bidders, $allUsers));

            foreach ($recipientIds as $userId) {
                $user = User::find($userId);
                if (!$user) {
                    continue;
                }

                event(new AuctionReminder($auction, $user, $timeRemaining));
                $totalSent++;
            }

            $this->info("حراج #{$auction->id}: {$totalSent} اعلان ارسال شد.");
        }

        $this->info("مجموعاً {$totalSent} اعلان ارسال شد.");
        return Command::SUCCESS;
    }
}
