<?php

namespace App\Listeners;

use App\Events\SharesGifted;
use App\Services\NotificationService;

class SendSharesGiftedNotifications
{
    public function __construct(private NotificationService $notifications)
    {
    }

    public function handle(SharesGifted $event): void
    {
        $holding = $event->holding;
        $user = $event->user;
        $stock = $event->stock;
        $quantity = $event->quantity;

        $title = 'هدیه سهام';
        $message = "{$quantity} سهم به عنوان هدیه به کیف سهام شما اضافه شد.";
        
        if ($event->description) {
            $message .= " ({$event->description})";
        }

        $url = route('holding.index');
        $context = [
            'stock_id' => $stock->id,
            'quantity' => $quantity,
            'description' => $event->description,
        ];

        $this->notifications->notifyUser(
            $user->id,
            $title,
            $message,
            $url,
            'shares.gifted',
            $context
        );
    }
}
