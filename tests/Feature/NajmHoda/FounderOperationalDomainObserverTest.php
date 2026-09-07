<?php

namespace Tests\Feature\NajmHoda;

use App\Models\Election;
use App\Models\ReportedMessage;
use App\Models\Setting;
use App\Observers\NajmHoda\FounderOperationalDomainObserver;
use App\Services\NajmHoda\Runtime\InMemoryRuntimeEventBus;
use App\Services\NajmHoda\Runtime\RuntimeEventBus;
use Tests\TestCase;

class FounderOperationalDomainObserverTest extends TestCase
{
    protected function bus(): InMemoryRuntimeEventBus
    {
        $bus = new InMemoryRuntimeEventBus(100);
        $this->app->instance(RuntimeEventBus::class, $bus);
        return $bus;
    }

    public function test_election_event_is_high_risk_and_contains_no_candidate_or_vote_data(): void
    {
        $bus = $this->bus();
        $election = new Election([
            'group_id' => 12,
            'starts_at' => now(),
            'ends_at' => now()->addDay(),
            'is_closed' => false,
        ]);
        $election->id = 44;

        (new FounderOperationalDomainObserver())->created($election);
        $events = $bus->recent('najm_hoda.input.governance.created', 1);

        $this->assertNotEmpty($events);
        $this->assertSame('high', data_get($events[0], 'payload.risk'));
        $this->assertSame(12, (int) data_get($events[0], 'payload.group_id'));
        $this->assertArrayNotHasKey('candidates', data_get($events[0], 'payload', []));
        $this->assertArrayNotHasKey('votes', data_get($events[0], 'payload', []));
    }

    public function test_moderation_event_excludes_report_text_and_notes(): void
    {
        $bus = $this->bus();
        $report = new ReportedMessage([
            'message_id' => 5,
            'group_id' => 9,
            'reported_by' => 2,
            'reason' => 'private reason',
            'description' => 'private description',
            'admin_note' => 'private note',
            'status' => 'escalated_to_admin',
            'escalated_to_admin' => true,
        ]);
        $report->id = 77;

        (new FounderOperationalDomainObserver())->created($report);
        $events = $bus->recent('najm_hoda.input.moderation.created', 1);
        $payload = data_get($events[0], 'payload', []);

        $this->assertTrue((bool) ($payload['escalated_to_admin'] ?? false));
        $this->assertArrayNotHasKey('reason', $payload);
        $this->assertArrayNotHasKey('description', $payload);
        $this->assertArrayNotHasKey('admin_note', $payload);
    }

    public function test_admin_setting_event_exposes_change_names_not_sensitive_values(): void
    {
        $bus = $this->bus();
        $settings = new Setting(['najm_summary' => 'secret summary']);
        $settings->id = 1;

        (new FounderOperationalDomainObserver())->created($settings);
        $events = $bus->recent('najm_hoda.input.admin_settings.created', 1);
        $payload = data_get($events[0], 'payload', []);

        $this->assertSame('high', $payload['risk'] ?? null);
        $this->assertArrayNotHasKey('najm_summary', $payload);
    }
}
