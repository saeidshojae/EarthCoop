<?php

namespace Tests\Feature\Elections;

use App\Temporal\Context\TemporalContextResolver;
use App\Temporal\Contracts\TemporalService;
use DateTimeImmutable;
use Tests\TestCase;

class ElectionTemporalPresentationTest extends TestCase
{
    public function test_same_deadline_renders_in_locale_calendar_without_changing_instant(): void
    {
        $temporal = app(TemporalService::class);
        $contexts = app(TemporalContextResolver::class);
        $deadline = new DateTimeImmutable('2026-10-01T13:30:00Z');

        $this->assertSame(
            '۱۴۰۵/۰۷/۰۹ ۱۳:۳۰',
            $temporal->dateTime($deadline, $contexts->forLocale('fa', 'UTC'), 'short'),
        );
        $this->assertSame(
            '2026-10-01 13:30',
            $temporal->dateTime($deadline, $contexts->forLocale('en', 'UTC'), 'short'),
        );
    }

    public function test_election_notification_paths_do_not_depend_directly_on_jalali_library(): void
    {
        foreach ([
            app_path('Listeners/SendElectionStartedNotifications.php'),
            app_path('Console/Commands/SendElectionReminders.php'),
        ] as $path) {
            $source = file_get_contents($path);
            $this->assertStringNotContainsString('Morilog\\Jalali', $source, $path);
            $this->assertStringNotContainsString('Jalalian', $source, $path);
        }
    }
}
