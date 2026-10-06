<?php

namespace Tests\Feature\Communication;

use App\Enums\Communication\CommunicationClassification;
use App\Jobs\Communication\ResolveCommunicationRunAudience;
use App\Models\CommunicationRule;
use App\Models\CommunicationRuleSchedule;
use App\Models\CommunicationRun;
use App\Models\CommunicationSenderIdentity;
use App\Models\CommunicationTemplate;
use App\Models\User;
use App\Services\Communication\CommunicationScheduleService;
use App\Services\Communication\CommunicationTemplateService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class ScheduledRuleTest extends TestCase
{
    use RefreshDatabase;

    public function test_due_schedule_creates_one_run_and_scheduler_reentry_does_not_duplicate_it(): void
    {
        Queue::fake();
        $now = CarbonImmutable::parse('2026-09-30 09:00:00', 'Asia/Tehran');
        $users = User::factory()->count(3)->create();
        [$rule, $schedule] = $this->scheduledRule($users->pluck('id')->all(), $now);

        $service = app(CommunicationScheduleService::class);
        $this->assertSame(1, $service->processDue($now));
        $this->assertSame(0, $service->processDue($now));

        $this->assertSame(1, CommunicationRun::query()->where('communication_rule_id', $rule->id)->count());
        $run = CommunicationRun::query()->where('communication_rule_id', $rule->id)->firstOrFail();
        $this->assertSame('pending', $run->status);
        $this->assertSame('schedule:'.$rule->id.':'.$now->utc()->format('YmdHis'), $run->run_key);

        $schedule->refresh();
        $this->assertNotNull($schedule->last_run_at);
        $this->assertTrue($schedule->next_run_at->greaterThan($now));
        Queue::assertPushed(ResolveCommunicationRunAudience::class, 1);
    }

    public function test_delayed_daily_schedule_uses_planned_occurrence_and_preserves_local_wall_clock_across_dst(): void
    {
        Queue::fake();

        $scheduledFor = CarbonImmutable::parse('2026-03-07 09:00:00', 'America/New_York');
        $processedAt = CarbonImmutable::parse('2026-03-07 09:05:00', 'America/New_York');
        $users = User::factory()->count(2)->create();
        [$rule, $schedule] = $this->scheduledRule($users->pluck('id')->all(), $scheduledFor);

        $schedule->forceFill([
            'frequency' => 'daily',
            'timezone' => 'America/New_York',
            'timezone_mode' => 'explicit',
            'next_run_at' => $scheduledFor->utc(),
        ])->save();

        $this->assertSame(1, app(CommunicationScheduleService::class)->processDue($processedAt));

        $run = CommunicationRun::query()->where('communication_rule_id', $rule->id)->firstOrFail();
        $this->assertSame(
            'schedule:'.$rule->id.':'.$scheduledFor->utc()->format('YmdHis'),
            $run->run_key,
        );

        $schedule->refresh();
        $this->assertSame(
            $processedAt->utc()->format('Y-m-d H:i:s'),
            $schedule->last_run_at?->utc()->format('Y-m-d H:i:s'),
        );
        $this->assertSame(
            '2026-03-08 13:00:00',
            $schedule->next_run_at?->utc()->format('Y-m-d H:i:s'),
        );
        $this->assertSame(
            '09:00',
            $schedule->next_run_at?->timezone('America/New_York')->format('H:i'),
        );
    }

    public function test_audience_resolution_is_chunked_for_large_specific_user_sets(): void
    {
        Queue::fake();
        $now = CarbonImmutable::parse('2026-09-30 09:00:00', 'Asia/Tehran');
        $users = User::factory()->count(5)->create();
        [$rule] = $this->scheduledRule($users->pluck('id')->all(), $now);

        app(CommunicationScheduleService::class)->processDue($now);
        $run = CommunicationRun::query()->where('communication_rule_id', $rule->id)->firstOrFail();

        // Isolate child-chunk assertions from the one root resolver job queued by processDue().
        Queue::fake();
        $job = new ResolveCommunicationRunAudience($run->id, 2);
        $job->handle(app(\App\Services\Communication\CommunicationAudienceRegistry::class));

        Queue::assertPushed(ResolveCommunicationRunAudience::class, 3);
    }

    public function test_due_rule_console_command_processes_due_schedules(): void
    {
        Queue::fake();
        $now = CarbonImmutable::parse('2026-09-30 09:00:00', 'Asia/Tehran');
        CarbonImmutable::setTestNow($now);
        $users = User::factory()->count(2)->create();
        [$rule] = $this->scheduledRule($users->pluck('id')->all(), $now);

        try {
            $this->artisan('communications:process-due')->assertSuccessful();
        } finally {
            CarbonImmutable::setTestNow();
        }

        $this->assertSame(1, CommunicationRun::query()->where('communication_rule_id', $rule->id)->count());
        Queue::assertPushed(ResolveCommunicationRunAudience::class, 1);
    }

    /** @param array<int,int> $userIds */
    private function scheduledRule(array $userIds, CarbonImmutable $now): array
    {
        $sender = CommunicationSenderIdentity::query()->create([
            'key' => 'scheduled-test',
            'email' => 'scheduled-test@earthcoop.ir',
            'display_name' => 'EarthCoop Scheduled Test',
            'is_active' => true,
        ]);
        $template = CommunicationTemplate::query()->create([
            'key' => 'test.scheduled.weekly',
            'name' => 'Scheduled test fixture',
            'classification' => CommunicationClassification::Operational,
            'is_active' => true,
        ]);
        app(CommunicationTemplateService::class)->publish(
            $template, 'fa', 'گزارش هفتگی', '<p>گزارش هفتگی ارث‌کوپ</p>', [], $sender,
        );
        $rule = CommunicationRule::query()->create([
            'key' => 'test.scheduled.weekly.rule',
            'name' => 'Scheduled test fixture',
            'trigger_type' => 'scheduled',
            'audience_definition' => ['key' => 'specific.user', 'user_ids' => $userIds],
            'communication_template_id' => $template->id,
            'communication_sender_identity_id' => $sender->id,
            'classification' => CommunicationClassification::Operational,
            'priority' => 2,
            'is_active' => true,
        ]);
        $schedule = CommunicationRuleSchedule::query()->create([
            'communication_rule_id' => $rule->id,
            'frequency' => 'weekly',
            'schedule_definition' => ['interval' => 1],
            'timezone' => 'Asia/Tehran',
            'timezone_mode' => 'system',
            'next_run_at' => $now->utc(),
        ]);

        return [$rule, $schedule];
    }
}
