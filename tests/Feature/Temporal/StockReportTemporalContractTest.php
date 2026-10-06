<?php

namespace Tests\Feature\Temporal;

use App\Temporal\Context\TemporalContextResolver;
use App\Temporal\Contracts\TemporalService;
use Tests\TestCase;

class StockReportTemporalContractTest extends TestCase
{
    public function test_jalali_and_gregorian_report_filters_resolve_to_same_canonical_day(): void
    {
        $temporal = app(TemporalService::class);
        $contexts = app(TemporalContextResolver::class);

        $jalali = $temporal->parseDate('1405/07/09', $contexts->forLocale('fa', 'UTC'));
        $gregorian = $temporal->parseDate('2026-10-01', $contexts->forLocale('en', 'UTC'));

        $this->assertSame('2026-10-01', $jalali->toCanonical());
        $this->assertSame($jalali->toCanonical(), $gregorian->toCanonical());
        $this->assertSame(
            $temporal->startOfDay($jalali, $contexts->forLocale('fa', 'UTC'))->format('c'),
            $temporal->startOfDay($gregorian, $contexts->forLocale('en', 'UTC'))->format('c'),
        );
        $this->assertSame(
            $temporal->endOfDay($jalali, $contexts->forLocale('fa', 'UTC'))->format('c'),
            $temporal->endOfDay($gregorian, $contexts->forLocale('en', 'UTC'))->format('c'),
        );
    }

    public function test_stock_report_controller_and_views_do_not_bypass_temporal_boundary(): void
    {
        $paths = [
            app_path('Modules/Stock/Controllers/StockReportController.php'),
            app_path('Modules/Stock/Views/admin_reports/financial.blade.php'),
            app_path('Modules/Stock/Views/admin_reports/auction_performance.blade.php'),
        ];

        foreach ($paths as $path) {
            $source = file_get_contents($path);
            $this->assertStringNotContainsString('Morilog\\Jalali', $source, $path);
            $this->assertStringNotContainsString('Jalalian::', $source, $path);
            $this->assertStringNotContainsString('verta(', $source, $path);
        }

        $financial = file_get_contents(app_path('Modules/Stock/Views/admin_reports/financial.blade.php'));
        $performance = file_get_contents(app_path('Modules/Stock/Views/admin_reports/auction_performance.blade.php'));

        $this->assertStringContainsString('<x-temporal.date-input', $financial);
        $this->assertStringContainsString('<x-temporal.date-input', $performance);
    }
}
