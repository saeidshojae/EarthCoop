<?php

namespace Tests\Feature\Temporal;

use Tests\TestCase;

class CommunicationAdminTemporalPresentationTest extends TestCase
{
    public function test_communication_admin_surfaces_use_shared_temporal_datetime_components(): void
    {
        $automation = file_get_contents(resource_path('views/admin/communications/automations/index.blade.php'));
        $campaigns = file_get_contents(resource_path('views/admin/communications/campaigns/index.blade.php'));
        $history = file_get_contents(resource_path('views/admin/communications/history.blade.php'));
        $template = file_get_contents(resource_path('views/admin/communications/templates/show.blade.php'));

        $this->assertStringContainsString(
            '<x-temporal.date-time :value="$rule->schedule->next_run_at" :timezone="$rule->schedule->timezone" />',
            $automation,
        );
        $this->assertStringContainsString(
            '<x-temporal.date-time :value="$campaign->scheduled_at" />',
            $campaigns,
        );
        $this->assertStringContainsString(
            '<x-temporal.date-time :value="$recipient->queued_at" />',
            $history,
        );
        $this->assertStringContainsString(
            '<x-temporal.date-time :value="$recipient->sent_at ?? $recipient->failed_at" />',
            $history,
        );
        $this->assertStringContainsString(
            '<x-temporal.date-time :value="$version->published_at" />',
            $template,
        );

        foreach ([$automation, $campaigns, $history, $template] as $view) {
            $this->assertStringNotContainsString("format('Y-m-d H:i')", $view);
        }
    }
}
