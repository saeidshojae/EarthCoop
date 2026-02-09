<?php

namespace App\Listeners;

use App\Events\BidLost;
use App\Services\NotificationService;

class SendBidLostNotifications
{
    public function __construct(private NotificationService $notifications)
    {
    }

    public function handle(BidLost $event): void
    {
        $bid = $event->bid;
        $auction = $event->auction;
        $user = $event->user;

        $title = 'پیشنهاد شما در حراج برنده نشد';
        $message = "متأسفانه پیشنهاد شما در حراج #{$auction->id} برنده نشد. مبلغ " . number_format($bid->total_value) . " تومان آزاد شد و به موجودی کیف پول شما اضافه شد.";
        
        $url = route('auction.show', $auction);
        $context = [
            'auction_id' => $auction->id,
            'bid_id' => $bid->id,
            'amount' => $bid->total_value,
        ];

        $this->notifications->notifyUser(
            $user->id,
            $title,
            $message,
            $url,
            'auction.lost',
            $context
        );
    }
}
