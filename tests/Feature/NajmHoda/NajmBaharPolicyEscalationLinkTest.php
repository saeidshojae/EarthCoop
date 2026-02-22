<?php

namespace Tests\Feature\NajmHoda;

use App\Services\NajmHoda\Runtime\InMemoryRuntimeEventBus;
use App\Services\NajmHoda\Runtime\NajmHodaAutonomyApprovalService;
use App\Services\NajmHoda\Runtime\NajmHodaDomainEventPolicyLinkService;
use App\Services\NotificationService;
use Tests\TestCase;

class NajmBaharPolicyEscalationLinkTest extends TestCase
{
    public function test_failed_najm_bahar_service_event_triggers_policy_and_escalation_links(): void
    {
        config([
            'cache.default' => 'array',
            'najm-hoda.runtime.domain_policy_link.enabled' => true,
            'najm-hoda.runtime.domain_policy_link.request_approval_on_failures' => true,
            'najm-hoda.runtime.domain_policy_link.approval_risk_levels' => ['medium', 'high'],
            'najm-hoda.runtime.autonomy.human_escalation.notify_admins' => false,
        ]);

        $bus = new InMemoryRuntimeEventBus(200);
        $approval = new NajmHodaAutonomyApprovalService($bus, app(NotificationService::class));
        $link = new NajmHodaDomainEventPolicyLinkService($bus, $approval);

        $link->ingest('najm_hoda.input.najm_bahar.service.investment.process_payment.failed', [
            'risk' => 'medium',
            'scope' => 'economy:najm-bahar',
            'error' => 'simulated failure',
        ]);

        $blocked = $bus->recent('najm_hoda.autonomy.safety.blocked', 1);
        $alerts = $bus->recent('najm_hoda.autonomy.governance.alert.raised', 1);
        $approvals = $bus->recent('najm_hoda.autonomy.approval.requested', 1);

        $this->assertNotEmpty($blocked);
        $this->assertSame('domain_service_failed', (string) data_get($blocked[0], 'payload.reason'));
        $this->assertNotEmpty($alerts);
        $this->assertSame('domain_service_failed', (string) data_get($alerts[0], 'payload.type'));
        $this->assertNotEmpty($approvals);
        $this->assertSame('review_najm_bahar_service_event', (string) data_get($approvals[0], 'payload.action'));
    }

    public function test_link_service_ignores_non_target_event_prefix(): void
    {
        config([
            'cache.default' => 'array',
            'najm-hoda.runtime.domain_policy_link.enabled' => true,
            'najm-hoda.runtime.autonomy.human_escalation.notify_admins' => false,
        ]);

        $bus = new InMemoryRuntimeEventBus(100);
        $approval = new NajmHodaAutonomyApprovalService($bus, app(NotificationService::class));
        $link = new NajmHodaDomainEventPolicyLinkService($bus, $approval);

        $link->ingest('najm_hoda.input.support.ticket.created', ['risk' => 'low']);

        $blocked = $bus->recent('najm_hoda.autonomy.safety.blocked', 1);
        $alerts = $bus->recent('najm_hoda.autonomy.governance.alert.raised', 1);
        $approvals = $bus->recent('najm_hoda.autonomy.approval.requested', 1);

        $this->assertEmpty($blocked);
        $this->assertEmpty($alerts);
        $this->assertEmpty($approvals);
    }

    public function test_failed_support_service_event_triggers_policy_link(): void
    {
        config([
            'cache.default' => 'array',
            'najm-hoda.runtime.domain_policy_link.enabled' => true,
            'najm-hoda.runtime.domain_policy_link.request_approval_on_failures' => true,
            'najm-hoda.runtime.domain_policy_link.approval_risk_levels' => ['medium', 'high'],
            'najm-hoda.runtime.autonomy.human_escalation.notify_admins' => false,
        ]);

        $bus = new InMemoryRuntimeEventBus(200);
        $approval = new NajmHodaAutonomyApprovalService($bus, app(NotificationService::class));
        $link = new NajmHodaDomainEventPolicyLinkService($bus, $approval);

        $link->ingest('najm_hoda.input.support.service.email_integration.process.failed', [
            'risk' => 'medium',
            'scope' => 'support:email',
        ]);

        $blocked = $bus->recent('najm_hoda.autonomy.safety.blocked', 1);
        $alerts = $bus->recent('najm_hoda.autonomy.governance.alert.raised', 1);
        $approvals = $bus->recent('najm_hoda.autonomy.approval.requested', 1);

        $this->assertSame('support', (string) data_get($blocked[0], 'payload.domain'));
        $this->assertSame('domain_service_failed', (string) data_get($alerts[0], 'payload.type'));
        $this->assertSame('review_support_service_event', (string) data_get($approvals[0], 'payload.action'));
    }

    public function test_content_delete_event_raises_governance_alert_and_approval(): void
    {
        config([
            'cache.default' => 'array',
            'najm-hoda.runtime.domain_policy_link.enabled' => true,
            'najm-hoda.runtime.domain_policy_link.request_approval_on_failures' => true,
            'najm-hoda.runtime.domain_policy_link.approval_risk_levels' => ['medium', 'high'],
            'najm-hoda.runtime.autonomy.human_escalation.notify_admins' => false,
        ]);

        $bus = new InMemoryRuntimeEventBus(200);
        $approval = new NajmHodaAutonomyApprovalService($bus, app(NotificationService::class));
        $link = new NajmHodaDomainEventPolicyLinkService($bus, $approval);

        $link->ingest('najm_hoda.input.content.service.page.deleted', [
            'risk' => 'medium',
            'scope' => 'content',
        ]);

        $alerts = $bus->recent('najm_hoda.autonomy.governance.alert.raised', 1);
        $approvals = $bus->recent('najm_hoda.autonomy.approval.requested', 1);
        $blocked = $bus->recent('najm_hoda.autonomy.safety.blocked', 1);

        $this->assertSame('domain_sensitive_mutation', (string) data_get($alerts[0], 'payload.type'));
        $this->assertSame('review_content_service_event', (string) data_get($approvals[0], 'payload.action'));
        $this->assertEmpty($blocked);
    }

    public function test_failed_auth_service_event_triggers_policy_link(): void
    {
        config([
            'cache.default' => 'array',
            'najm-hoda.runtime.domain_policy_link.enabled' => true,
            'najm-hoda.runtime.domain_policy_link.request_approval_on_failures' => true,
            'najm-hoda.runtime.domain_policy_link.approval_risk_levels' => ['medium', 'high'],
            'najm-hoda.runtime.autonomy.human_escalation.notify_admins' => false,
        ]);

        $bus = new InMemoryRuntimeEventBus(200);
        $approval = new NajmHodaAutonomyApprovalService($bus, app(NotificationService::class));
        $link = new NajmHodaDomainEventPolicyLinkService($bus, $approval);

        $link->ingest('najm_hoda.input.auth.service.lifecycle.login.failed', [
            'risk' => 'medium',
            'scope' => 'auth',
        ]);

        $blocked = $bus->recent('najm_hoda.autonomy.safety.blocked', 1);
        $approvals = $bus->recent('najm_hoda.autonomy.approval.requested', 1);

        $this->assertSame('auth', (string) data_get($blocked[0], 'payload.domain'));
        $this->assertSame('review_auth_service_event', (string) data_get($approvals[0], 'payload.action'));
    }
}
