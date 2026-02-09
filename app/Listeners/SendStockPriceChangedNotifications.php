<?php

namespace App\Listeners;

use App\Events\StockPriceChanged;
use App\Modules\Stock\Models\Holding;
use App\Services\NotificationService;

class SendStockPriceChangedNotifications
{
    public function __construct(private NotificationService $notifications)
    {
    }

    public function handle(StockPriceChanged $event): void
    {
        $stock = $event->stock;
        $oldPrice = $event->oldPrice;
        $newPrice = $event->newPrice;
        $oldValuation = $event->oldValuation;
        $newValuation = $event->newValuation;

        // ارسال اعلان به همه سهامداران
        $shareholders = Holding::where('stock_id', $stock->id)
            ->where('quantity', '>', 0)
            ->with('user')
            ->get();

        $messages = [];
        if ($oldPrice !== null && $newPrice !== null && $oldPrice != $newPrice) {
            $messages[] = "قیمت پایه هر سهم از " . number_format($oldPrice) . " به " . number_format($newPrice) . " تومان تغییر کرد.";
        }
        if ($oldValuation !== null && $newValuation !== null && $oldValuation != $newValuation) {
            $messages[] = "ارزش پایه استارتاپ از " . number_format($oldValuation) . " به " . number_format($newValuation) . " تومان تغییر کرد.";
        }

        if (empty($messages)) {
            return;
        }

        $title = 'تغییر قیمت سهام';
        $message = implode(' ', $messages);
        
        $url = route('stock.book');
        $context = [
            'stock_id' => $stock->id,
            'old_price' => $oldPrice,
            'new_price' => $newPrice,
            'old_valuation' => $oldValuation,
            'new_valuation' => $newValuation,
        ];

        foreach ($shareholders as $holding) {
            if ($holding->user) {
                $this->notifications->notifyUser(
                    $holding->user->id,
                    $title,
                    $message,
                    $url,
                    'stock.price_changed',
                    $context
                );
            }
        }
    }
}
