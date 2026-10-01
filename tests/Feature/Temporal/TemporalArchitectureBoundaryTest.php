<?php

namespace Tests\Feature\Temporal;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Tests\TestCase;

class TemporalArchitectureBoundaryTest extends TestCase
{
    /**
     * Files already migrated to the Temporal boundary. Once a path enters this
     * list it may never regain a direct dependency on Jalali/Verta formatting.
     * The list expands until the final repository-wide guard can replace it.
     *
     * @return array<int,string>
     */
    private function migratedPaths(): array
    {
        return [
            'app/Console/Commands/SendElectionReminders.php',
            'app/Exports/NajmBaharTransactionsExport.php',
            'app/Http/Controllers/Admin/NajmBaharAnalyticsController.php',
            'app/Http/Controllers/Admin/ReportController.php',
            'app/Http/Controllers/Admin/TemporalSafeUserController.php',
            'app/Http/Controllers/Admin/TicketController.php',
            'app/Http/Controllers/Auth/Register/Step1Controller.php',
            'app/Listeners/SendElectionStartedNotifications.php',
            'app/Modules/Stock/Controllers/StockReportController.php',
            'app/Rules/JalaliMinimumAge.php',
            'app/Services/Communication/Context/WeeklyMemberReportContextBuilder.php',
            'app/Services/TicketSlaService.php',
            'resources/views/elections/responsibility-offer-confirm.blade.php',
            'resources/views/groups/partials/poll.blade.php',
            'app/Modules/Stock/Views/admin_reports/financial.blade.php',
            'app/Modules/Stock/Views/admin_reports/auction_performance.blade.php',
            'app/Modules/Stock/Views/auction_show.blade.php',
            'app/Modules/Stock/Views/auction_list.blade.php',
            'app/Modules/Stock/Views/admin_auction_show.blade.php',
            'app/Modules/Stock/Views/admin_auction_list.blade.php',
            'resources/js/temporal-input.js',
        ];
    }

    public function test_migrated_surfaces_never_call_legacy_calendar_apis_directly(): void
    {
        $forbidden = [
            'Morilog\\Jalali',
            'Jalalian::',
            'CalendarUtils::',
            'verta(',
            "toLocaleDateString('fa-IR'",
            'toLocaleDateString("fa-IR"',
        ];
        $offenders = [];

        foreach ($this->migratedPaths() as $path) {
            $contents = file_get_contents(base_path($path));
            foreach ($forbidden as $needle) {
                if (str_contains($contents, $needle)) {
                    $offenders[] = "{$path}: {$needle}";
                }
            }
        }

        $this->assertSame(
            [],
            $offenders,
            "Migrated temporal surfaces may not regain direct legacy calendar dependencies:\n" . implode("\n", $offenders),
        );
    }

    public function test_temporal_subsystem_hides_morilog_behind_jalali_adapter(): void
    {
        $root = app_path('Temporal');
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root));
        $offenders = [];

        foreach ($iterator as $file) {
            if (! $file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }

            $relative = str_replace(base_path() . DIRECTORY_SEPARATOR, '', $file->getPathname());
            $contents = file_get_contents($file->getPathname());

            if (
                str_contains($contents, 'Morilog\\Jalali')
                && $relative !== 'app/Temporal/Calendars/JalaliCalendarAdapter.php'
            ) {
                $offenders[] = $relative;
            }
        }

        $this->assertSame([], $offenders, 'Morilog must remain an implementation detail of JalaliCalendarAdapter.');
    }

    public function test_temporal_core_contains_no_earthcoop_chronicle_or_era_logic(): void
    {
        $root = app_path('Temporal');
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root));
        $offenders = [];

        foreach ($iterator as $file) {
            if (! $file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }

            $contents = file_get_contents($file->getPathname());
            if (preg_match('/EarthCoop(?:Year|Era|Epoch)|Chronicle/i', $contents) === 1) {
                $offenders[] = str_replace(base_path() . DIRECTORY_SEPARATOR, '', $file->getPathname());
            }
        }

        $this->assertSame([], $offenders, 'Chronicle/EarthCoop-era logic must stay outside Temporal core.');
    }
}
