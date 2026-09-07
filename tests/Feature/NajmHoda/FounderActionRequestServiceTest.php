<?php

namespace Tests\Feature\NajmHoda;

use App\Services\NajmHoda\FounderOps\FounderActionAuthorityService;
use App\Services\NajmHoda\FounderOps\FounderActionRequestService;
use App\Services\NajmHoda\FounderOps\FounderDelegationGrantService;
use App\Services\NajmHoda\FounderOps\FounderManagedDomainRegistry;
use App\Services\NajmHoda\Runtime\NajmHodaAutonomyApprovalService;
use Tests\TestCase;

class FounderActionRequestServiceTest extends TestCase
{
    public function test_approval_required_action_enters_existing_approval_queue_with_minimized_context(): void
    {
        $approvals = $this->createMock(NajmHodaAutonomyApprovalService::class);
        $approvals->expects($this->once())
            ->method('requestApproval')
            ->with(
                $this->callback(fn (array $plan): bool =>
                    $plan['action'] === 'founder_ops:email.send_email'
                    && $plan['domain'] === 'email'
                    && $plan['domain_action'] === 'send_email'
                    && $plan['mode'] === 'approval_required'
                ),
                $this->callback(fn (array $context): bool =>
                    ($context['entity_type'] ?? null) === 'email_template'
                    && ($context['entity_id'] ?? null) === 12
                    && ! array_key_exists('body', $context)
                    && ! array_key_exists('recipient', $context)
                )
            )
            ->willReturn(['id' => 'approval-1', 'status' => 'pending']);

        $service = new FounderActionRequestService(
            app(FounderActionAuthorityService::class),
            app(FounderManagedDomainRegistry::class),
            app(FounderDelegationGrantService::class),
            $approvals
        );

        $result = $service->prepare('email', 'send_email', [
            'entity_type' => 'email_template',
            'entity_id' => 12,
            'body' => 'private body',
            'recipient' => 'private@example.com',
        ]);

        $this->assertSame('awaiting_approval', $result['status']);
        $this->assertSame('approval-1', data_get($result, 'approval_request.id'));
    }

    public function test_forbidden_action_never_enters_approval_queue(): void
    {
        $approvals = $this->createMock(NajmHodaAutonomyApprovalService::class);
        $approvals->expects($this->never())->method('requestApproval');

        $service = new FounderActionRequestService(
            app(FounderActionAuthorityService::class),
            app(FounderManagedDomainRegistry::class),
            app(FounderDelegationGrantService::class),
            $approvals
        );

        $result = $service->prepare('governance', 'alter_vote');

        $this->assertSame('blocked', $result['status']);
        $this->assertSame('forbidden', data_get($result, 'decision.mode'));
    }

    public function test_delegated_safe_action_stays_disabled_without_explicit_delegation(): void
    {
        $approvals = $this->createMock(NajmHodaAutonomyApprovalService::class);
        $service = new FounderActionRequestService(
            app(FounderActionAuthorityService::class),
            app(FounderManagedDomainRegistry::class),
            app(FounderDelegationGrantService::class),
            $approvals
        );

        $result = $service->prepare('support', 'classify_ticket');

        $this->assertSame('delegation_required', $result['status']);
        $this->assertFalse(data_get($result, 'decision.may_execute'));
    }
}
