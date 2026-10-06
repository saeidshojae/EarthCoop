<?php

namespace Tests\Feature\Communication;

use App\Enums\Communication\CommunicationClassification;
use App\Jobs\Communication\ResolveCommunicationRunAudience;
use App\Models\Communication;
use App\Models\CommunicationPreference;
use App\Models\CommunicationRule;
use App\Models\CommunicationRuleSchedule;
use App\Models\CommunicationRun;
use App\Models\CommunicationSenderIdentity;
use App\Models\CommunicationTemplate;
use App\Models\User;
use App\Services\Communication\CommunicationAudienceRegistry;
use App\Services\Communication\CommunicationTemplateService;
use App\Temporal\Context\TemporalContextResolver;
use App\Temporal\Contracts\TemporalService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

final class WeeklyReportSchedulePreferenceTest extends TestCase
{
    use RefreshDatabase;

    private const TEMPLATE_KEY = 'test.reports.member.weekly';

    public function test_role_member_run_builds_context_per_recipient_and_honors_operational_preference(): void
    {
        Queue::fake();
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-09-30 08:00:00', 'Asia/Tehran'));

        try {
            $allowed = User::factory()->create([
                'first_name' => 'عضو',
                'last_name' => 'اول',
                'status' => 'active',
                'is_system' => false,
            ]);
            $suppressed = User::factory()->create([
                'first_name' => 'عضو',
                'last_name' => 'دوم',
                'status' => 'active',
                'is_system' => false,
            ]);
            User::factory()->create([
                'first_name' => 'سامانه',
                'last_name' => 'داخلی',
                'status' => 'active',
                'is_system' => true,
            ]);

            CommunicationPreference::query()->create([
                'user_id' => $suppressed->id,
                'topic_key' => self::TEMPLATE_KEY,
                'channel' => 'email',
                'preference' => 'off',
                'frequency' => 'weekly',
            ]);

            $sender = CommunicationSenderIdentity::query()->create([
                'key' => 'reports-test',
                'email' => 'reports-test@earthcoop.ir',
                'display_name' => 'EarthCoop Reports',
                'is_active' => true,
            ]);
            $template = CommunicationTemplate::query()->create([
                'key' => self::TEMPLATE_KEY,
                'name' => 'Weekly member report test fixture',
                'classification' => CommunicationClassification::Operational,
                'is_active' => true,
            ]);
            app(CommunicationTemplateService::class)->publish(
                $template,
                'fa',
                'گزارش هفتگی {{display_name}}',
                '<p>{{display_name}} — {{groups_count}}</p>',
                [
                    'period_start' => ['type' => 'string', 'required' => true],
                    'period_end' => ['type' => 'string', 'required' => true],
                    'display_name' => ['type' => 'string', 'required' => true],
                    'groups_count' => ['type' => 'integer', 'required' => true],
                    'open_elections_count' => ['type' => 'integer', 'required' => true],
                    'open_polls_count' => ['type' => 'integer', 'required' => true],
                    'unread_notifications_count' => ['type' => 'integer', 'required' => true],
                ],
                $sender,
            );

            $rule = CommunicationRule::query()->create([
                'key' => self::TEMPLATE_KEY.'.test-rule',
                'name' => 'Weekly member report test',
                'trigger_type' => 'scheduled',
                'audience_definition' => ['key' => 'role.member'],
                'communication_template_id' => $template->id,
                'communication_sender_identity_id' => $sender->id,
                'classification' => CommunicationClassification::Operational,
                'priority' => 2,
                'is_active' => true,
            ]);
            CommunicationRuleSchedule::query()->create([
                'communication_rule_id' => $rule->id,
                'frequency' => 'weekly',
                'schedule_definition' => ['interval' => 1],
                'timezone' => 'America/New_York',
                'timezone_mode' => 'explicit',
                'next_run_at' => CarbonImmutable::parse('2026-03-15 03:30:00', 'UTC'),
            ]);

            $run = CommunicationRun::query()->create([
                'communication_rule_id' => $rule->id,
                'run_key' => 'weekly-member-schedule-boundary',
                'status' => 'pending',
                'scheduled_for' => CarbonImmutable::parse('2026-03-08 04:30:00', 'UTC'),
                'started_at' => now(),
            ]);

            (new ResolveCommunicationRunAudience($run->id, 50, 0))
                ->handle(app(CommunicationAudienceRegistry::class));

            $communications = Communication::query()
                ->where('communication_run_id', $run->id)
                ->orderBy('id')
                ->get();

            $this->assertCount(2, $communications);
            $this->assertSame(
                ['عضو اول', 'عضو دوم'],
                $communications->pluck('context_snapshot.display_name')->sort()->values()->all(),
            );
            $this->assertSame(
                2,
                $communications->pluck('deduplication_key')->unique()->count(),
            );

            $allowedContext = $communications
                ->first(fn (Communication $communication) => $communication->recipients()->where('user_id', $allowed->id)->exists())
                ?->context_snapshot ?? [];
            $temporalContext = app(TemporalContextResolver::class)->forRecipient($allowed);
            $this->assertSame(
                app(TemporalService::class)->date('2026-02-28', $temporalContext, 'short'),
                $allowedContext['period_start'] ?? null,
            );
            $this->assertSame(
                app(TemporalService::class)->date('2026-03-06', $temporalContext, 'short'),
                $allowedContext['period_end'] ?? null,
            );

            $allowedRecipient = $communications
                ->first(fn (Communication $communication) => $communication->recipients()->where('user_id', $allowed->id)->exists())
                ?->recipients()->where('user_id', $allowed->id)->firstOrFail();
            $suppressedRecipient = $communications
                ->first(fn (Communication $communication) => $communication->recipients()->where('user_id', $suppressed->id)->exists())
                ?->recipients()->where('user_id', $suppressed->id)->firstOrFail();

            $this->assertSame('queued', $allowedRecipient->status->value ?? $allowedRecipient->status);
            $this->assertSame('suppressed', $suppressedRecipient->status->value ?? $suppressedRecipient->status);
            $this->assertSame('operational_default_on', $allowedRecipient->preference_decision['reason']);
            $this->assertSame('user_off', $suppressedRecipient->preference_decision['reason']);

            $run->refresh();
            $this->assertSame(2, $run->matched_count);
            $this->assertSame(1, $run->eligible_count);
            $this->assertSame(1, $run->suppressed_count);
            $this->assertSame(1, $run->queued_count);
        } finally {
            CarbonImmutable::setTestNow();
        }
    }
}
