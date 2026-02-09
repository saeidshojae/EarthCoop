<?php

namespace App\Listeners;

use App\Events\WalletReleased;
use App\Services\NotificationService;

class SendWalletReleasedNotifications
{
    public function __construct(private NotificationService $notifications)
    {
    }

    public function handle(WalletReleased $event): void
    {
        $wallet = $event->wallet;
        $user = $event->user;
        $amount = $event->amount;
        $bid = $event->bid;

        $title = 'مبلغ آزاد شد';
        $message = "مبلغ " . number_format($amount) . " تومان از پیشنهاد شما آزاد شد و به موجودی کیف پول اضافه شد.";
        
        if ($bid && $bid->auction) {
            $message .= " (حراج #{$bid->auction->id})";
            $url = route('auction.show', $bid->auction);
            $context = [
                'auction_id' => $bid->auction->id,
                'bid_id' => $bid->id,
                'amount' => $amount,
            ];
        } else {
            $url = route('wallet.index');
            $context = [
                'amount' => $amount,
                'description' => $event->description,
            ];
        }

        $this->notifications->notifyUser(
            $user->id,
            $title,
            $message,
            $url,
            'wallet.released',
            $context
        );
    }
}
