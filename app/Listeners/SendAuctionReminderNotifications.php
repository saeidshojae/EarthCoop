<?php

namespace App\Listeners;

use App\Events\AuctionReminder;
use App\Services\NotificationService;

class SendAuctionReminderNotifications
{
    public function __construct(private NotificationService $notifications)
    {
    }

    public function handle(AuctionReminder $event): void
    {
        $auction = $event->auction;
        $user = $event->user;
        $timeRemaining = $event->timeRemaining;

        $title = 'یادآوری: حراج در حال پایان';
        $message = "حراج #{$auction->id} تا {$timeRemaining} به پایان می‌رسد. فرصت را از دست ندهید!";
        
        $url = route('auction.show', $auction);
        $context = [
            'auction_id' => $auction->id,
            'time_remaining' => $timeRemaining,
        ];

        $this->notifications->notifyUser(
            $user->id,
            $title,
            $message,
            $url,
            'auction.reminder',
            $context
        );
    }
}
