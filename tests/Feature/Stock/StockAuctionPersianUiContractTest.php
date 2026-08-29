<?php

namespace Tests\Feature\Stock;

use Tests\TestCase;

class StockAuctionPersianUiContractTest extends TestCase
{
    public function test_auction_views_do_not_leak_raw_backend_vocabulary(): void
    {
        $paths = [
            base_path('app/Modules/Stock/Views/auction_show.blade.php'),
            base_path('app/Modules/Stock/Views/admin_auction_list.blade.php'),
            base_path('app/Modules/Stock/Views/admin_auction_show.blade.php'),
        ];

        $contents = collect($paths)->map(fn (string $path) => file_get_contents($path))->implode("\n");

        foreach (['uniform_price', 'running', 'manual', 'GOL', 'canonical', 'quote', 'Najm Bahar', 'Bahar'] as $rawTerm) {
            $this->assertStringNotContainsString($rawTerm, $contents, "Raw backend/UI term leaked: {$rawTerm}");
        }

        foreach (['قیمت یکسان', 'در حال اجرا', 'تسویه دستی', 'گل', 'نجم بهار', 'نرخ معتبر و زمان‌دار'] as $persianTerm) {
            $this->assertStringContainsString($persianTerm, $contents, "Expected Persian UI term missing: {$persianTerm}");
        }
    }
}
