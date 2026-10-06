<?php

namespace Tests\Feature\Temporal;

use Tests\TestCase;

final class CommunicationTemporalArchitectureTest extends TestCase
{
    public function test_communication_human_time_inputs_and_outputs_stay_on_temporal_components(): void
    {
        $create = file_get_contents(resource_path('views/admin/communications/campaigns/create.blade.php'));
        $automation = file_get_contents(resource_path('views/admin/communications/automations/index.blade.php'));
        $campaigns = file_get_contents(resource_path('views/admin/communications/campaigns/index.blade.php'));
        $history = file_get_contents(resource_path('views/admin/communications/history.blade.php'));
        $template = file_get_contents(resource_path('views/admin/communications/templates/show.blade.php'));

        $this->assertStringContainsString('<x-temporal.date-time-input name="scheduled_at"', $create);
        $this->assertStringNotContainsString('datetime-local', $create);

        foreach ([$automation, $campaigns, $history, $template] as $view) {
            $this->assertStringNotContainsString("format('Y-m-d H:i')", $view);
        }
    }

    public function test_communication_schedulers_use_canonical_clocks_and_planned_occurrences(): void
    {
        $ruleCommand = file_get_contents(app_path('Console/Commands/CommunicationProcessDueRules.php'));
        $campaignCommand = file_get_contents(app_path('Console/Commands/CommunicationProcessDueCampaigns.php'));
        $scheduleService = file_get_contents(app_path('Services/Communication/CommunicationScheduleService.php'));
        $campaignService = file_get_contents(app_path('Services/Communication/CommunicationCampaignService.php'));
        $audienceJob = file_get_contents(app_path('Jobs/Communication/ResolveCommunicationRunAudience.php'));

        $this->assertStringContainsString("CarbonImmutable::now('UTC')", $ruleCommand);
        $this->assertStringContainsString("CarbonImmutable::now('UTC')", $campaignCommand);

        $this->assertStringContainsString('$scheduledFor = $schedule->next_run_at?->copy()->utc();', $scheduleService);
        $this->assertStringContainsString('$runKey = \'schedule:\'.$rule->id.\':\'.$scheduledFor->format(\'YmdHis\');', $scheduleService);
        $this->assertStringContainsString('nextRunAt($schedule, $scheduledFor)', $scheduleService);
        $this->assertStringNotContainsString('nextRunAt($schedule, $now)', $scheduleService);

        $this->assertStringContainsString('processDueCampaigns(CarbonInterface $now', $campaignService);
        $this->assertStringContainsString('$clock = $now->copy()->utc();', $campaignService);

        $this->assertStringContainsString('$run->scheduled_for ?? $run->started_at ?? now()', $audienceJob);
        $this->assertStringContainsString('->setTimezone($timezone)', $audienceJob);
    }
}
