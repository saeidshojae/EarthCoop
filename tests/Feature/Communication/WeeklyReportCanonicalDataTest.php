<?php

namespace Tests\Feature\Communication;

use App\Models\CommunicationRule;
use App\Models\CommunicationTemplate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class WeeklyReportCanonicalDataTest extends TestCase
{
    use RefreshDatabase;

    public function test_three_operational_weekly_report_templates_rules_and_schedules_are_seeded(): void
    {
        $contracts = [
            'reports.member.weekly' => 'role.member',
            'reports.manager.weekly' => 'role.manager',
            'reports.inspector.weekly' => 'role.inspector',
        ];

        foreach ($contracts as $templateKey => $audienceKey) {
            $template = CommunicationTemplate::query()->where('key', $templateKey)->firstOrFail();
            $this->assertTrue((bool) $template->is_active);
            $this->assertSame('operational', $template->classification->value ?? $template->classification);

            $version = $template->versions()
                ->where('locale', 'fa')
                ->whereNotNull('published_at')
                ->orderByDesc('version')
                ->firstOrFail();
            $this->assertSame(1, (int) $version->version);
            $this->assertNotEmpty($version->variables_schema);

            $rule = CommunicationRule::query()
                ->where('key', $templateKey.'.rule')
                ->with('schedule')
                ->firstOrFail();

            $this->assertTrue((bool) $rule->is_active);
            $this->assertSame('scheduled', $rule->trigger_type);
            $this->assertSame($audienceKey, $rule->audience_definition['key']);
            $this->assertSame($template->id, $rule->communication_template_id);
            $this->assertSame('operational', $rule->classification->value ?? $rule->classification);

            $this->assertNotNull($rule->schedule);
            $this->assertSame('weekly', $rule->schedule->frequency);
            $this->assertSame(1, (int) $rule->schedule->schedule_definition['interval']);
            $this->assertNotNull($rule->schedule->next_run_at);
        }
    }
}
