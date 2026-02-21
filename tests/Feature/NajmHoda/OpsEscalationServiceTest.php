<?php

namespace Tests\Feature\NajmHoda;

use App\Services\NajmHoda\Runtime\InMemoryRuntimeEventBus;
use App\Services\NajmHoda\Runtime\NajmHodaOpsEscalationService;
use App\Services\NotificationService;
use App\Services\TicketTriageService;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class OpsEscalationServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'cache.default' => 'array',
            'najm-hoda.runtime.ops.escalation.enabled' => true,
            'najm-hoda.runtime.ops.escalation.notify_admins' => false,
            'najm-hoda.runtime.ops.escalation.cooldown_seconds' => 600,
            'najm-hoda.runtime.ops.escalation.max_incidents_per_run' => 3,
        ]);

        Cache::flush();
    }

    public function test_escalation_dry_run_does_not_create_ticket(): void
    {
        $service = new NajmHodaOpsEscalationService(
            new InMemoryRuntimeEventBus(100),
            app(TicketTriageService::class),
            app(NotificationService::class)
        );

        $snapshot = [
            'status' => 'critical',
            'metrics' => [
                'error_rate_percent' => 50,
                'unresolved_requests' => 12,
            ],
        ];

        $incidents = [[
            'severity' => 'critical',
            'code' => 'OPS_CRITICAL_HEALTH',
            'title' => 'Critical health',
            'details' => [],
        ]];

        $result = $service->escalate($snapshot, $incidents, true);

        $this->assertCount(1, $result);
        $this->assertSame('dry_run', $result[0]['action']);
    }

    public function test_escalation_skips_when_cooldown_is_active(): void
    {
        $service = new NajmHodaOpsEscalationService(
            new InMemoryRuntimeEventBus(100),
            app(TicketTriageService::class),
            app(NotificationService::class)
        );

        $snapshot = [
            'status' => 'warning',
            'metrics' => [
                'error_rate_percent' => 20,
                'unresolved_requests' => 6,
            ],
        ];

        $incidents = [[
            'severity' => 'warning',
            'code' => 'OPS_WARNING_HEALTH',
            'title' => 'Warning health',
            'details' => ['response_failed' => 2],
        ]];

        Cache::put('najm_hoda:ops:escalation:OPS_WARNING_HEALTH', 123, now()->addMinutes(10));
        $result = $service->escalate($snapshot, $incidents, false);

        $this->assertCount(1, $result);
        $this->assertSame('skipped', $result[0]['action']);
        $this->assertSame('OPS_WARNING_HEALTH', $result[0]['incident_code']);
    }
}
