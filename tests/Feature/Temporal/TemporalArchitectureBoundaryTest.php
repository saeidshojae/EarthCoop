<?php

namespace Tests\Feature\Temporal;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Tests\TestCase;

class TemporalArchitectureBoundaryTest extends TestCase
{
    private function migratedPaths(): array
    {
        return [
            'app/Console/Commands/SendElectionReminders.php',
            'app/Exports/NajmBaharTransactionsExport.php',
            'app/Http/Controllers/Admin/NajmBaharAnalyticsController.php',
            'app/Http/Controllers/Admin/ReportController.php',
            'app/Http/Controllers/Admin/TemporalSafeUserController.php',
            'app/Http/Controllers/Admin/UserController.php',
            'app/Http/Controllers/Admin/TicketController.php',
            'app/Http/Controllers/Auth/Register/Step1Controller.php',
            'app/Http/Controllers/Profile/ProfileController.php',
            'app/Listeners/SendElectionStartedNotifications.php',
            'app/Modules/Stock/Controllers/AuctionController.php',
            'app/Modules/Stock/Controllers/CanonicalAdminAuctionController.php',
            'app/Modules/Stock/Controllers/CanonicalAuctionController.php',
            'app/Modules/Stock/Controllers/StockController.php',
            'app/Modules/Stock/Controllers/StockReportController.php',
            'app/Rules/JalaliMinimumAge.php',
            'app/Console/Commands/CommunicationProcessDueCampaigns.php',
            'app/Console/Commands/CommunicationProcessDueRules.php',
            'app/Http/Controllers/Admin/Communication/AutomationController.php',
            'app/Http/Controllers/Admin/Communication/CampaignController.php',
            'app/Jobs/Communication/ResolveCommunicationRunAudience.php',
            'app/Models/CommunicationRun.php',
            'app/Services/Communication/CommunicationCampaignService.php',
            'app/Services/Communication/CommunicationScheduleService.php',
            'app/Services/Communication/Context/WeeklyMemberReportContextBuilder.php',
            'resources/views/admin/communications/automations/index.blade.php',
            'resources/views/admin/communications/campaigns/create.blade.php',
            'resources/views/admin/communications/campaigns/index.blade.php',
            'resources/views/admin/communications/history.blade.php',
            'resources/views/admin/communications/templates/show.blade.php',
            'app/Services/NajmHoda/NajmHodaGroupAssistantService.php',
            'app/Services/TicketSlaService.php',
            'resources/views/Stock/admin_holdings_show.blade.php',
            'resources/views/admin/announcements/index.blade.php',
            'resources/views/admin/emails/index.blade.php',
            'resources/views/admin/najm-bahar/accounts/transactions.blade.php',
            'resources/views/admin/najm-bahar/audit-logs/index.blade.php',
            'resources/views/admin/najm-bahar/index.blade.php',
            'resources/views/admin/najm-bahar/logs/index.blade.php',
            'resources/views/admin/pages/index.blade.php',
            'resources/views/admin/rule/index.blade.php',
            'resources/views/admin/user/create.blade.php',
            'resources/views/admin/user/edit.blade.php',
            'resources/views/admin/user/transactions.blade.php',
            'resources/views/elections/responsibility-offer-confirm.blade.php',
            'resources/views/emails/ticket-created.blade.php',
            'resources/views/emails/ticket-reply.blade.php',
            'resources/views/groups/partials/comment.blade.php',
            'resources/views/groups/partials/group_hero.blade.php',
            'resources/views/groups/partials/message.blade.php',
            'resources/views/groups/partials/poll.blade.php',
            'resources/views/history/index.blade.php',
            'resources/views/history/poll.blade.php',
            'resources/views/my-invation-code.blade.php',
            'resources/views/najm-bahar/agreement.blade.php',
            'resources/views/najm-bahar/audit-logs/index.blade.php',
            'resources/views/partials/comments.blade.php',
            'resources/views/profile/member-invitations.blade.php',
            'resources/views/profile/partials/general.blade.php',
            'resources/views/terms.blade.php',
            'resources/views/user/tickets/index.blade.php',
            'app/Modules/Stock/Views/admin_auction_create.blade.php',
            'app/Modules/Stock/Views/admin_reports/financial.blade.php',
            'app/Modules/Stock/Views/admin_reports/auction_performance.blade.php',
            'app/Modules/Stock/Views/auction_show.blade.php',
            'app/Modules/Stock/Views/auction_list.blade.php',
            'app/Modules/Stock/Views/admin_auction_show.blade.php',
            'app/Modules/Stock/Views/admin_auction_list.blade.php',
            'app/Modules/Stock/Views/holding_show.blade.php',
            'resources/js/temporal-input.js',
        ];
    }

    private function knownLegacyDebt(): array
    {
        return [
        ];
    }

    private function forbiddenLegacyNeedles(): array
    {
        return [
            'Morilog\\Jalali',
            'Jalalian::',
            'CalendarUtils::',
            'verta(',
            "toLocaleDateString('fa-IR'",
            'toLocaleDateString("fa-IR"',
            'date_default_timezone_set(',
        ];
    }

    public function test_migrated_surfaces_never_call_legacy_calendar_apis_directly(): void
    {
        $offenders = [];
        foreach ($this->migratedPaths() as $path) {
            $contents = file_get_contents(base_path($path));
            foreach ($this->forbiddenLegacyNeedles() as $needle) {
                if (str_contains($contents, $needle)) {
                    $offenders[] = "{$path}: {$needle}";
                }
            }
        }

        $this->assertSame([], $offenders, "Migrated temporal surfaces may not regain direct legacy calendar dependencies:\n" . implode("\n", $offenders));
    }

    public function test_active_repository_surfaces_match_known_legacy_calendar_debt(): void
    {
        $roots = [app_path(), resource_path('views'), resource_path('js'), base_path('routes')];
        $offenders = [];

        foreach ($roots as $root) {
            if (! is_dir($root)) {
                continue;
            }
            $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root));
            foreach ($iterator as $file) {
                if (! $file->isFile()) {
                    continue;
                }
                $relative = str_replace(base_path() . DIRECTORY_SEPARATOR, '', $file->getPathname());
                if ($relative === 'app/Temporal/Calendars/JalaliCalendarAdapter.php') {
                    continue;
                }
                $contents = file_get_contents($file->getPathname());
                foreach ($this->forbiddenLegacyNeedles() as $needle) {
                    if (str_contains($contents, $needle)) {
                        $offenders[] = "{$relative}: {$needle}";
                    }
                }
            }
        }

        sort($offenders);
        $this->assertSame(
            $this->knownLegacyDebt(),
            $offenders,
            "Active repository temporal legacy debt changed. New entries are forbidden; migrated entries must be removed from the baseline:\n" . implode("\n", $offenders),
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
            if (str_contains($contents, 'Morilog\\Jalali') && $relative !== 'app/Temporal/Calendars/JalaliCalendarAdapter.php') {
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

    public function test_first_party_blade_surfaces_do_not_reintroduce_raw_human_datetime_rendering(): void
    {
        $roots = [resource_path('views'), app_path('Modules')];
        $needles = [
            'type="datetime-local"',
            'type="date"',
            "type='datetime-local'",
            'diffForHumans(',
            "->format('Y/m/d')",
            "->format('Y/m/d H:i')",
            "->format('Y-m-d H:i')",
            'persianDatepicker(',
            'unpkg.com/persian-datepicker',
            'vendor/persian-datepicker',
            'profile-assets/css/persian-datepicker',
        ];
        $offenders = [];

        foreach ($roots as $root) {
            if (! is_dir($root)) {
                continue;
            }

            $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root));
            foreach ($iterator as $file) {
                if (! $file->isFile() || ! str_ends_with($file->getFilename(), '.blade.php')) {
                    continue;
                }

                $relative = str_replace(base_path() . DIRECTORY_SEPARATOR, '', $file->getPathname());
                $contents = file_get_contents($file->getPathname());

                foreach ($needles as $needle) {
                    if (str_contains($contents, $needle)) {
                        $offenders[] = "{$relative}: {$needle}";
                    }
                }
            }
        }

        sort($offenders);
        $this->assertSame(
            [],
            $offenders,
            "Human-facing Blade surfaces must use EarthCoop Temporal components instead of raw browser/localized datetime formatting:\n" . implode("\n", $offenders),
        );
    }

}
