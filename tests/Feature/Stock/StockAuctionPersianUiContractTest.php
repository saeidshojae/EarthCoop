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

    public function test_stock_admin_dashboard_and_reports_do_not_reintroduce_legacy_money_ui(): void
    {
        $dashboard = file_get_contents(base_path('app/Modules/Stock/Views/admin_stock_info.blade.php'));
        $auctionReport = file_get_contents(base_path('app/Modules/Stock/Views/admin_reports/auction_performance.blade.php'));
        $investorReport = file_get_contents(base_path('app/Modules/Stock/Views/admin_reports/investors.blade.php'));
        $financialReport = file_get_contents(base_path('app/Modules/Stock/Views/admin_reports/financial.blade.php'));
        $controller = file_get_contents(base_path('app/Modules/Stock/Controllers/StockReportController.php'));
        $reports = $auctionReport . "\n" . $investorReport . "\n" . $financialReport;

        foreach (['مدیریت کیف پول‌ها', 'تومان', 'ریال'] as $legacyTerm) {
            $this->assertStringNotContainsString($legacyTerm, $dashboard, "Legacy Stock dashboard term leaked: {$legacyTerm}");
            $this->assertStringNotContainsString($legacyTerm, $reports, "Legacy Stock report money term leaked: {$legacyTerm}");
        }

        foreach (['گل', 'بهار'] as $unit) {
            $this->assertStringContainsString($unit, $dashboard, "Canonical dashboard unit missing: {$unit}");
            $this->assertStringContainsString($unit, $reports, "Canonical report unit missing: {$unit}");
        }

        foreach ([
            "->base_share_price ?? 0",
            "pluck('price')->",
            '(\$bid->price ?? 0)',
            "->max('price')",
        ] as $legacyRead) {
            $this->assertStringNotContainsString($legacyRead, $controller, "Legacy monetary read remains in StockReportController: {$legacyRead}");
        }

        foreach (['base_share_price_gol', 'price_gol'] as $canonicalRead) {
            $this->assertStringContainsString($canonicalRead, $controller, "Canonical report read missing: {$canonicalRead}");
        }
    }
}
