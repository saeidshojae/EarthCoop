<?php

namespace Tests\Feature\NajmHoda;

use App\Listeners\CaptureNajmHodaAuthLifecycle;
use App\Models\User;
use App\Services\NajmHoda\Runtime\InMemoryRuntimeEventBus;
use App\Services\NajmHoda\Runtime\NajmHodaAutonomyApprovalService;
use App\Services\NajmHoda\Runtime\NajmHodaDomainEventPolicyLinkService;
use App\Services\NotificationService;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Tests\TestCase;

class AuthLifecycleInstrumentationTest extends TestCase
{
    public function test_listener_emits_login_success_event(): void
    {
        config([
            'cache.default' => 'array',
            'najm-hoda.enabled' => true,
            'najm-hoda.runtime.domain_policy_link.enabled' => true,
            'najm-hoda.runtime.autonomy.human_escalation.notify_admins' => false,
        ]);

        $bus = new InMemoryRuntimeEventBus(200);
        $approval = new NajmHodaAutonomyApprovalService($bus, app(NotificationService::class));
        $listener = new CaptureNajmHodaAuthLifecycle($bus, new NajmHodaDomainEventPolicyLinkService($bus, $approval));

        $user = new User();
        $user->id = 42;

        $listener->handle(new Login('web', $user, false));

        $events = $bus->recent('najm_hoda.input.auth.service.lifecycle.login.succeeded', 1);
        $this->assertNotEmpty($events);
        $this->assertSame(42, (int) data_get($events[0], 'payload.user_id'));
    }

    public function test_listener_emits_login_failed_and_triggers_policy_link(): void
    {
        config([
            'cache.default' => 'array',
            'najm-hoda.enabled' => true,
            'najm-hoda.runtime.domain_policy_link.enabled' => true,
            'najm-hoda.runtime.domain_policy_link.request_approval_on_failures' => true,
            'najm-hoda.runtime.domain_policy_link.approval_risk_levels' => ['medium', 'high'],
            'najm-hoda.runtime.autonomy.human_escalation.notify_admins' => false,
        ]);

        $bus = new InMemoryRuntimeEventBus(300);
        $approval = new NajmHodaAutonomyApprovalService($bus, app(NotificationService::class));
        $listener = new CaptureNajmHodaAuthLifecycle($bus, new NajmHodaDomainEventPolicyLinkService($bus, $approval));

        $listener->handle(new Failed('web', null, ['email' => 'x@example.com']));

        $failed = $bus->recent('najm_hoda.input.auth.service.lifecycle.login.failed', 1);
        $blocked = $bus->recent('najm_hoda.autonomy.safety.blocked', 1);

        $this->assertNotEmpty($failed);
        $this->assertSame('auth', (string) data_get($blocked[0], 'payload.domain'));
    }
}
