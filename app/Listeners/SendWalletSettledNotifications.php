<?php

namespace App\Listeners;

use App\Events\WalletSettled;
use App\Services\NotificationService;

class SendWalletSettledNotifications
{
    public function __construct(private NotificationService $notifications)
    {
    }

    public function handle(WalletSettled $event): void
    {
        $wallet = $event->wallet;
        $user = $event->user;
        $amount = $event->amount;
        $bid = $event->bid;

        $title = 'تسویه انجام شد';
        $message = "مبلغ " . number_format($amount) . " تومان از کیف پول شما برای خرید سهام کسر شد.";
        
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
            'wallet.settled',
            $context
        );
    }
}
