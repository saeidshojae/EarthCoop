<?php

namespace Tests\Feature\Temporal;

use App\Temporal\Context\TemporalContextResolver;
use App\Temporal\Contracts\TemporalService;
use Tests\TestCase;

class AdminReportTemporalContractTest extends TestCase
{
    public function test_admin_report_filters_accept_equivalent_jalali_and_gregorian_days(): void
    {
        $temporal = app(TemporalService::class);
        $contexts = app(TemporalContextResolver::class);
        $fa = $contexts->forLocale('fa', 'UTC');
        $en = $contexts->forLocale('en', 'UTC');

        $jalali = $temporal->parseDate('1405/07/09', $fa);
        $gregorian = $temporal->parseDate('2026-10-01', $en);

        $this->assertSame($jalali->toCanonical(), $gregorian->toCanonical());
        $this->assertSame(
            $temporal->startOfDay($jalali, $fa)->format('c'),
            $temporal->startOfDay($gregorian, $en)->format('c'),
        );
        $this->assertSame(
            $temporal->endOfDay($jalali, $fa)->format('c'),
            $temporal->endOfDay($gregorian, $en)->format('c'),
        );
    }

    public function test_report_controller_keeps_temporal_logic_behind_boundary(): void
    {
        $source = file_get_contents(app_path('Http/Controllers/Admin/ReportController.php'));

        $this->assertStringContainsString('parseDate(', $source);
        $this->assertStringContainsString('startOfDay(', $source);
        $this->assertStringContainsString('endOfDay(', $source);
        $this->assertStringNotContainsString('Morilog\\Jalali', $source);
        $this->assertStringNotContainsString('Jalalian::', $source);
        $this->assertStringNotContainsString('whereDate(\'created_at\'', $source);
    }

    public function test_report_moderation_contracts_remain_present_after_temporal_refactor(): void
    {
        $source = file_get_contents(app_path('Http/Controllers/Admin/ReportController.php'));

        $this->assertStringContainsString("'status' => 'required|in:pending,reviewed,resolved,rejected,archived'", $source);
        $this->assertStringContainsString("'action' => 'required|in:approve,reject,archive,delete'", $source);
        $this->assertStringContainsString("$report->reviewed_at = now();", $source);
        $this->assertStringContainsString("$report->delete();", $source);
    }
}
