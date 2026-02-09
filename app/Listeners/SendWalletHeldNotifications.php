<?php

namespace App\Listeners;

use App\Events\WalletHeld;
use App\Services\NotificationService;

class SendWalletHeldNotifications
{
    public function __construct(private NotificationService $notifications)
    {
    }

    public function handle(WalletHeld $event): void
    {
        $wallet = $event->wallet;
        $user = $event->user;
        $amount = $event->amount;
        $auction = $event->auction;

        $title = 'مبلغ مسدود شد';
        $message = "مبلغ " . number_format($amount) . " تومان برای پیشنهاد شما در حراج #{$auction->id} مسدود شد.";
        
        $url = route('auction.show', $auction);
        $context = [
            'auction_id' => $auction->id,
            'bid_id' => $event->bid?->id,
            'amount' => $amount,
        ];

        $this->notifications->notifyUser(
            $user->id,
            $title,
            $message,
            $url,
            'wallet.held',
            $context
        );
    }
}
