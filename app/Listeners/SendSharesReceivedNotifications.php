<?php

namespace App\Listeners;

use App\Events\SharesReceived;
use App\Services\NotificationService;

class SendSharesReceivedNotifications
{
    public function __construct(private NotificationService $notifications)
    {
    }

    public function handle(SharesReceived $event): void
    {
        $holding = $event->holding;
        $user = $event->user;
        $quantity = $event->quantity;
        $auction = $event->auction;

        $title = 'سهام دریافت شد';
        $message = "{$quantity} سهم به کیف سهام شما اضافه شد.";
        
        if ($auction) {
            $message .= " (از حراج #{$auction->id})";
            $url = route('auction.show', $auction);
            $context = [
                'auction_id' => $auction->id,
                'bid_id' => $event->bid?->id,
                'quantity' => $quantity,
            ];
        } else {
            $url = route('holding.index');
            $context = [
                'quantity' => $quantity,
                'description' => $event->description,
            ];
        }

        $this->notifications->notifyUser(
            $user->id,
            $title,
            $message,
            $url,
            'shares.received',
            $context
        );
    }
}
