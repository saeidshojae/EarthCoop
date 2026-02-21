<?php

namespace Tests\Feature\NajmHoda;

use App\Services\NajmHoda\Runtime\InMemoryRuntimeEventBus;
use App\Services\NajmHoda\Runtime\NajmHodaAutonomyApprovalService;
use App\Services\NotificationService;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class AutonomyApprovalServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'cache.default' => 'array',
            'najm-hoda.runtime.autonomy.human_escalation.enabled' => true,
            'najm-hoda.runtime.autonomy.human_escalation.notify_admins' => false,
            'najm-hoda.runtime.autonomy.human_escalation.sla_minutes' => 30,
            'najm-hoda.runtime.autonomy.human_escalation.retention_minutes' => 60,
            'najm-hoda.runtime.autonomy.human_escalation.max_requests_history' => 200,
        ]);

        Cache::flush();
    }

    public function test_request_is_queued_and_visible_in_pending_feed(): void
    {
        $bus = new InMemoryRuntimeEventBus(100);
        $service = new NajmHodaAutonomyApprovalService($bus, app(NotificationService::class));

        $request = $service->requestApproval([
            'action' => 'run_ops_monitor',
            'risk' => 'medium',
            'mode' => 'apply',
        ], [
            'source' => 'test',
        ]);

        $this->assertSame('pending', (string) ($request['status'] ?? ''));
        $pending = $service->pending(10);
        $this->assertNotEmpty($pending);
        $this->assertSame((string) ($request['id'] ?? ''), (string) ($pending[0]['id'] ?? ''));
        $this->assertSame('within_sla', (string) ($pending[0]['sla_status'] ?? ''));
    }

    public function test_admin_decision_updates_request_status_and_emits_event(): void
    {
        $bus = new InMemoryRuntimeEventBus(100);
        $service = new NajmHodaAutonomyApprovalService($bus, app(NotificationService::class));

        $request = $service->requestApproval([
            'action' => 'run_ops_monitor',
            'risk' => 'high',
            'mode' => 'apply',
        ], []);

        $decision = $service->decide((string) ($request['id'] ?? ''), 'reject', 99, 'risk too high');

        $this->assertTrue((bool) ($decision['success'] ?? false));
        $this->assertSame('rejected', (string) data_get($decision, 'request.status', ''));
        $this->assertSame('risk too high', (string) data_get($decision, 'request.decision_reason', ''));

        $events = $bus->recent('najm_hoda.autonomy.approval.decided', 1);
        $this->assertNotEmpty($events);
        $this->assertSame('reject', data_get($events[0], 'payload.decision'));
    }
}
