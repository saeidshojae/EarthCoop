<?php

namespace Tests\Feature\NajmHoda;

use App\Services\NajmHoda\FounderOps\FounderDelegationGrantService;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class FounderDelegationGrantServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Cache::forget('najm_hoda:founder_ops:delegation_grants');
        config()->set('najm-hoda-founder-action-policy.founder_approval.user_ids', [99]);
    }

    public function test_unauthorized_identity_cannot_issue_delegation(): void
    {
        $result = app(FounderDelegationGrantService::class)->grant('support', 'classify_ticket', 7, 24);

        $this->assertFalse($result['success']);
        $this->assertSame('founder_identity_not_authorized', $result['reason']);
    }

    public function test_only_delegated_safe_capabilities_can_be_granted(): void
    {
        $service = app(FounderDelegationGrantService::class);

        $approvalAction = $service->grant('najm_bahar', 'execute_transaction', 99, 24);
        $forbiddenAction = $service->grant('najm_bahar', 'alter_ledger_history', 99, 24);

        $this->assertFalse($approvalAction['success']);
        $this->assertSame('action_not_delegatable', $approvalAction['reason']);
        $this->assertFalse($forbiddenAction['success']);
        $this->assertSame('action_not_delegatable', $forbiddenAction['reason']);
    }

    public function test_authorized_founder_can_issue_and_revoke_expiring_safe_grant(): void
    {
        $service = app(FounderDelegationGrantService::class);
        $created = $service->grant('support', 'classify_ticket', 99, 2);

        $this->assertTrue($created['success']);
        $this->assertTrue($service->isGranted('support', 'classify_ticket'));

        $revoked = $service->revoke($created['grant']['id'], 99);
        $this->assertTrue($revoked['success']);
        $this->assertFalse($service->isGranted('support', 'classify_ticket'));
    }
}
