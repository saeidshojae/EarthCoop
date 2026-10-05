<?php

namespace Tests\Feature\Communication;

use App\Enums\Communication\CommunicationClassification;
use App\Jobs\Communication\ResolveCommunicationRunAudience;
use App\Models\Communication;
use App\Models\CommunicationRule;
use App\Models\CommunicationRun;
use App\Models\CommunicationTemplate;
use App\Models\User;
use App\Services\Communication\CommunicationAudienceRegistry;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

final class SpecificUserWeeklyReportContextTest extends TestCase
{
    use RefreshDatabase;

    public function test_specific_user_weekly_member_report_builds_required_recipient_context(): void
    {
        Queue::fake();
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-10-05 20:00:00', 'Asia/Tehran'));

        try {
            $recipient = User::factory()->create([
                'first_name' => 'سعید',
                'last_name' => 'آزمون',
                'status' => 'active',
                'is_system' => false,
            ]);

            $template = CommunicationTemplate::query()
                ->where('key', 'reports.member.weekly')
                ->firstOrFail();

            $rule = CommunicationRule::query()->create([
                'key' => 'uat.specific-user.weekly-member-context',
                'name' => 'UAT specific user weekly member context',
                'trigger_type' => 'scheduled',
                'audience_definition' => [
                    'key' => 'specific.user',
                    'user_ids' => [$recipient->id],
                ],
                'communication_template_id' => $template->id,
                'communication_sender_identity_id' => null,
                'classification' => CommunicationClassification::Operational,
                'priority' => 2,
                'is_active' => true,
            ]);

            $run = CommunicationRun::query()->create([
                'communication_rule_id' => $rule->id,
                'run_key' => 'uat-specific-user-weekly-member-context',
                'status' => 'pending',
                'started_at' => now(),
            ]);

            (new ResolveCommunicationRunAudience($run->id, 50, 0))
                ->handle(app(CommunicationAudienceRegistry::class));

            $communication = Communication::query()
                ->where('communication_run_id', $run->id)
                ->firstOrFail();

            $this->assertSame('سعید آزمون', data_get($communication->context_snapshot, 'display_name'));
            $this->assertNotNull(data_get($communication->context_snapshot, 'period_start'));
            $this->assertNotNull(data_get($communication->context_snapshot, 'period_end'));
            $this->assertIsInt(data_get($communication->context_snapshot, 'groups_count'));

            $recipientRow = $communication->recipients()->where('user_id', $recipient->id)->firstOrFail();
            $this->assertSame('queued', $recipientRow->status->value ?? $recipientRow->status);
        } finally {
            CarbonImmutable::setTestNow();
        }
    }
}
