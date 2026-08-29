<?php

namespace Tests\Feature\Stock;

use Tests\TestCase;

class StockAuctionPersianUiContractTest extends TestCase
{
    public function test_auction_views_translate_backend_values_before_display(): void
    {
        $public = file_get_contents(base_path('app/Modules/Stock/Views/auction_show.blade.php'));
        $adminList = file_get_contents(base_path('app/Modules/Stock/Views/admin_auction_list.blade.php'));
        $adminShow = file_get_contents(base_path('app/Modules/Stock/Views/admin_auction_show.blade.php'));
        $contents = $public . "\n" . $adminList . "\n" . $adminShow;

        foreach (['قیمت یکسان', 'در حال اجرا', 'تسویه دستی', 'واحد قیمت‌گذاری: گل', 'نجم بهار', 'نرخ معتبر و زمان‌دار'] as $persianTerm) {
            $this->assertStringContainsString($persianTerm, $contents, "Expected Persian UI term missing: {$persianTerm}");
        }

        foreach ([
            "{{ \$auction->status ?? '—' }}",
            "{{ \$auction->type ?? '—' }}",
            "{{ \$auction->settlement_mode ?? '—' }}",
            "{{ strtoupper(\$auction->quote_unit ?? '—') }}",
        ] as $rawOutput) {
            $this->assertStringNotContainsString($rawOutput, $contents, "Raw backend value is rendered directly: {$rawOutput}");
        }

        foreach (['قیمت canonical', 'canonical گل', 'quote معتبر', 'Najm Bahar', 'Bahar جدید'] as $rawCopy) {
            $this->assertStringNotContainsString($rawCopy, $contents, "Technical copy leaked to Persian UI: {$rawCopy}");
        }
    }
}
